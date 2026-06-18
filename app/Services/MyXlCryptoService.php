<?php

namespace App\Services;

class MyXlCryptoService
{
    /**
     * Derive IV bytes from timestamp.
     */
    protected function deriveIv(int $xtimeMs): string
    {
        $sha = hash('sha256', (string) $xtimeMs);
        return substr($sha, 0, 16);
    }

    /**
     * Encrypt plaintext body to xdata using AES-CBC with derived IV.
     */
    public function encryptXData(string $plaintext, int $xtimeMs, string $xdataKey): string
    {
        $iv = $this->deriveIv($xtimeMs);
        $method = (strlen($xdataKey) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';
        $encrypted = openssl_encrypt($plaintext, $method, $xdataKey, OPENSSL_RAW_DATA, $iv);
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    /**
     * Decrypt xdata payload back to plaintext using AES-CBC with derived IV.
     */
    public function decryptXData(string $xdata, int $xtimeMs, string $xdataKey): string
    {
        $iv = $this->deriveIv($xtimeMs);
        $b64 = strtr($xdata, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $ct = base64_decode($b64);
        $method = (strlen($xdataKey) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';
        return openssl_decrypt($ct, $method, $xdataKey, OPENSSL_RAW_DATA, $iv) ?: '';
    }

    /**
     * Generate standard X-Signature.
     */
    public function makeXSignature(string $idToken, string $method, string $path, int $sigTimeSec, string $xApiBaseSecret): string
    {
        $key = "{$xApiBaseSecret};{$idToken};{$method};{$path};{$sigTimeSec}";
        $msg = "{$idToken};{$sigTimeSec};";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Generate X-Signature for payments.
     */
    public function makeXSignaturePayment(
        string $accessToken,
        int $sigTimeSec,
        string $packageCode,
        string $tokenPayment,
        string $paymentMethod,
        string $paymentFor,
        string $path,
        string $xApiBaseSecret
    ): string {
        $key = "{$xApiBaseSecret};{$sigTimeSec}#ae-hei_9Tee6he+Ik3Gais5=;POST;{$path};{$sigTimeSec}";
        $msg = "{$accessToken};{$tokenPayment};{$sigTimeSec};{$paymentFor};{$paymentMethod};{$packageCode};";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Generate Ax-Api-Signature for ForgeRock OpenID token flow.
     */
    public function makeAxApiSignature(string $tsForSign, string $contact, string $code, string $contactType, string $axApiSigKey): string
    {
        $preimage = "{$tsForSign}password{$contactType}{$contact}{$code}openid";
        $digest = hash_hmac('sha256', $preimage, $axApiSigKey, true);
        return base64_encode($digest);
    }

    /**
     * Generate X-Signature for bounty exchange.
     */
    public function makeXSignatureBounty(string $accessToken, int $sigTimeSec, string $packageCode, string $tokenPayment, string $xApiBaseSecret): string
    {
        $path = "api/v8/personalization/bounties-exchange";
        $key = "{$xApiBaseSecret};{$accessToken};{$sigTimeSec}#ae-hei_9Tee6he+Ik3Gais5=;POST;{$path};{$sigTimeSec}";
        $msg = "{$accessToken};{$tokenPayment};{$sigTimeSec};{$packageCode};";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Generate X-Signature for loyalty exchange.
     */
    public function makeXSignatureLoyalty(int $sigTimeSec, string $packageCode, string $tokenConfirmation, string $path, string $xApiBaseSecret): string
    {
        $key = "{$xApiBaseSecret};{$sigTimeSec}#ae-hei_9Tee6he+Ik3Gais5=;POST;{$path};{$sigTimeSec}";
        $msg = "{$tokenConfirmation};{$sigTimeSec};{$packageCode};";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Generate X-Signature for bounty allotment.
     */
    public function makeXSignatureBountyAllotment(int $sigTimeSec, string $packageCode, string $tokenConfirmation, string $path, string $destinationMsisdn, string $xApiBaseSecret): string
    {
        $key = "{$xApiBaseSecret};{$sigTimeSec}#ae-hei_9Tee6he+Ik3Gais5=;{$destinationMsisdn};POST;{$path};{$sigTimeSec}";
        $msg = "{$tokenConfirmation};{$sigTimeSec};{$destinationMsisdn};{$packageCode};";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Generate basic X-Signature.
     */
    public function makeXSignatureBasic(string $method, string $path, int $sigTimeSec, string $xApiBaseSecret): string
    {
        $key = "{$xApiBaseSecret};{$method};{$path};{$sigTimeSec}";
        $msg = "{$sigTimeSec};en;";
        return hash_hmac('sha512', $msg, $key);
    }

    /**
     * Encrypt MSISDN for Circle settings.
     */
    public function encryptCircleMsisdn(string $msisdn, string $encryptedFieldKey): string
    {
        $ivAscii = bin2hex(random_bytes(8));
        $method = (strlen($encryptedFieldKey) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';
        $ct = openssl_encrypt($msisdn, $method, $encryptedFieldKey, OPENSSL_RAW_DATA, $ivAscii);
        $ctB64 = rtrim(strtr(base64_encode($ct), '+/', '-_'), '=');
        return $ctB64 . $ivAscii;
    }

    /**
     * Decrypt MSISDN from Circle settings.
     */
    public function decryptCircleMsisdn(string $encryptedMsisdnB64, string $encryptedFieldKey): string
    {
        if (strlen($encryptedMsisdnB64) < 16) {
            return '';
        }
        $ivAscii = substr($encryptedMsisdnB64, -16);
        $b64Part = substr($encryptedMsisdnB64, 0, -16);
        $b64 = strtr($b64Part, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $ct = base64_decode($b64);
        $method = (strlen($encryptedFieldKey) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';
        return openssl_decrypt($ct, $method, $encryptedFieldKey, OPENSSL_RAW_DATA, $ivAscii) ?: '';
    }
}
