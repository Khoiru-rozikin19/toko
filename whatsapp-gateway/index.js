const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } = require('@whiskeysockets/baileys');
const express = require('express');
const cors = require('cors');
const pino = require('pino');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(cors());
app.use(express.json());

const PORT = 3000;
let sock = null;
let connectionStatus = 'connecting'; // connecting, qr, ready
let latestQr = null;

const authPath = path.join(__dirname, 'auth_info');

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(authPath);
    
    // Ambil versi whatsapp web terbaru untuk mencegah error 405
    const { version } = await fetchLatestBaileysVersion().catch(() => ({
        version: [2, 3000, 1017551065] // Fallback version
    }));

    sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'silent' }),
        browser: Browsers.ubuntu('Chrome'),
    });
    
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        if (qr) {
            connectionStatus = 'qr';
            latestQr = qr;
        }
        
        if (connection === 'close') {
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
            connectionStatus = 'connecting';
            latestQr = null;
            console.log('Connection closed due to ', lastDisconnect?.error, ', reconnecting: ', shouldReconnect);
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 5000);
            } else {
                // Clear session
                if (fs.existsSync(authPath)) {
                    fs.rmSync(authPath, { recursive: true, force: true });
                }
                setTimeout(connectToWhatsApp, 2000);
            }
        } else if (connection === 'open') {
            connectionStatus = 'ready';
            latestQr = null;
            console.log('WhatsApp connection opened successfully!');
        }
    });
    
    sock.ev.on('creds.update', saveCreds);
}

// API Endpoints
app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        qr: latestQr
    });
});

app.post('/send-message', async (req, res) => {
    const { phone, message } = req.body;
    
    if (!phone || !message) {
        return res.status(400).json({ error: 'Missing phone or message' });
    }
    
    if (connectionStatus !== 'ready') {
        return res.status(503).json({ error: 'WhatsApp is not ready' });
    }
    
    try {
        // Format phone: strip plus signs and make sure it has @s.whatsapp.net
        let formattedPhone = phone.replace(/[^0-9]/g, '');
        if (formattedPhone.startsWith('0')) {
            formattedPhone = '62' + formattedPhone.slice(1);
        }
        const jid = `${formattedPhone}@s.whatsapp.net`;
        
        const sentMsg = await sock.sendMessage(jid, { text: message });
        res.json({ success: true, messageId: sentMsg.key.id });
    } catch (err) {
        console.error('Error sending message:', err);
        res.status(500).json({ error: err.message });
    }
});

app.post('/send-group-message', async (req, res) => {
    const { groupId, message } = req.body;
    
    if (!groupId || !message) {
        return res.status(400).json({ error: 'Missing groupId or message' });
    }
    
    if (connectionStatus !== 'ready') {
        return res.status(503).json({ error: 'WhatsApp is not ready' });
    }
    
    try {
        let jid = groupId.trim();
        if (!jid.includes('@')) {
            jid = `${jid}@g.us`;
        }
        
        const sentMsg = await sock.sendMessage(jid, { text: message });
        res.json({ success: true, messageId: sentMsg.key.id });
    } catch (err) {
        console.error('Error sending group message:', err);
        res.status(500).json({ error: err.message });
    }
});

app.post('/disconnect', async (req, res) => {
    try {
        if (sock) {
            await sock.logout();
        }
        if (fs.existsSync(authPath)) {
            fs.rmSync(authPath, { recursive: true, force: true });
        }
        connectionStatus = 'connecting';
        latestQr = null;
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Start Express
app.listen(PORT, '127.0.0.1', () => {
    console.log(`WhatsApp API Gateway server running internally at http://127.0.0.1:${PORT}`);
    connectToWhatsApp().catch(err => console.error('Failed to connect to WhatsApp:', err));
});
