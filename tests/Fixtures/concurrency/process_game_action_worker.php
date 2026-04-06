<?php

declare(strict_types=1);

use App\Enums\GameActionType;
use App\Models\User;
use App\Services\Game\ProcessGameAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

/**
 * Small standalone worker used by concurrency tests.
 *
 * Each child process boots Laravel fresh, points the sqlite connection at the same
 * temporary database file, waits for a shared start time and then submits one action.
 * The parent test reads a single JSON result from stdout.
 */
try {
    $inputPath = $argv[1] ?? null;

    if (! is_string($inputPath) || ! is_file($inputPath)) {
        throw new RuntimeException('Missing worker input payload.');
    }

    /** @var array{database_path: string, start_at: float|int|string, user_id: int, action: string, payload: array<string, mixed>} $input */
    $input = json_decode((string) file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);

    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $input['database_path']);

    DB::disconnect('sqlite');
    DB::purge('sqlite');
    DB::reconnect('sqlite');
    DB::statement('PRAGMA journal_mode = WAL');
    DB::statement('PRAGMA busy_timeout = 5000');

    $startAt = (float) $input['start_at'];

    while (microtime(true) < $startAt) {
        usleep(1_000);
    }

    $user = User::query()->findOrFail((int) $input['user_id']);
    $message = app(ProcessGameAction::class)->handle(
        $user,
        GameActionType::from((string) $input['action']),
        $input['payload'],
    );

    echo json_encode([
        'ok' => true,
        'message' => $message,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'exception_class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
