<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$admin = User::where('role', 'admin')->first();
if ($admin) {
    Auth::login($admin);
} else {
    // Create temporary admin if none exists
    $admin = User::create([
        'name' => 'Admin Test',
        'email' => 'admin_test@vpn.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);
    Auth::login($admin);
}

try {
    $controller = app(App\Http\Controllers\AdminController::class);
    $view = $controller->transactions(request());
    echo "SUCCESS RENDERING:\n";
    echo substr($view->render(), 0, 500) . "\n...\n";
} catch (\Exception $e) {
    echo "ERROR DETECTED:\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . " Line " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
