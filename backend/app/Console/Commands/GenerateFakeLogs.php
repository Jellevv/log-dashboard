<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//php artisan logs:fake

class GenerateFakeLogs extends Command
{
    protected $signature = 'logs:fake 
                            {--interval=1 : Seconds between entries} 
                            {--level=mixed : error, warning, info, or mixed}';

    protected $description = 'Write fake Craft CMS log entries to test the log dashboard live refresh';

    private string $logPath;

    private array $logComponents = [
        'craft\elements\User',
        'craft\db\Connection',
        'craft\web\Application',
        'craft\services\Elements',
        'craft\services\Images',
        'craft\helpers\Html',
        'modules\site\controllers\OrderController',
        'modules\site\services\PaymentService',
    ];

    private array $messages = [
        'error' => [
            [
                'message' => 'Call to undefined method craft\elements\User::getFullName()',
                'trace' => true,
            ],
            [
                'message' => 'SQLSTATE[42S22]: Column not found: 1054 Unknown column "deleted_by" in "field list"',
                'trace' => true,
            ],
            [
                'message' => 'Trying to access array offset on value of type null',
                'trace' => true,
            ],
            [
                'message' => 'Unable to find template "shop/_product.twig"',
                'trace' => false,
            ],
            [
                'message' => 'Maximum execution time of 30 seconds exceeded',
                'trace' => true,
            ],
        ],
        'warning' => [
            [
                'message' => 'Tried to restore session from the identity cookie, but the saved user agent does not match the current request\'s (Mozilla/5.0)',
                'trace' => false,
            ],
            [
                'message' => 'Failed to connect to mail server, retrying in 5s',
                'trace' => false,
            ],
            [
                'message' => 'Cache key "products_all" exceeded max size, skipping cache write',
                'trace' => false,
            ],
            [
                'message' => 'Deprecated: str_contains(): Passing null to parameter #1 of type string',
                'trace' => false,
            ],
        ],
        'info' => [
            [
                'message' => 'GET /admin/dashboard 200',
                'trace' => false,
            ],
            [
                'message' => 'POST /api/v1/orders 201',
                'trace' => false,
            ],
            [
                'message' => 'User login: admin@example.com',
                'trace' => false,
            ],
            [
                'message' => 'Queue job ProcessInvoice completed in 1.2s',
                'trace' => false,
            ],
            [
                'message' => 'Craft CMS updated to 4.8.1',
                'trace' => false,
            ],
        ],
    ];

    public function handle(): void
    {
        $this->logPath = storage_path('logs/test.log');

        $interval = (int) $this->option('interval');
        $levelOpt = $this->option('level');

        $this->info("Writing fake Craft logs to test.log every {$interval}s — Ctrl+C to stop");
        $this->line('');

        $i = 0;
        while (true) {
            $level = $levelOpt === 'mixed'
                ? ['error', 'error', 'warning', 'info'][$i % 4]
                : $levelOpt;

            $pool = $this->messages[$level];
            $entry = $pool[array_rand($pool)];
            $message = $entry['message'];
            $component = $this->logComponents[array_rand($this->logComponents)];

            $this->writeCraftEntry($level, $component, $message, $entry['trace']);

            $this->line("<fg={$this->color($level)}>[{$level}]</> [{$component}] {$message}");

            $i++;
            sleep($interval);
        }
    }

    private function writeCraftEntry(string $level, string $component, string $message, bool $withTrace): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $line = "{$timestamp} [{$level}][{$component}] {$message}";

        if ($withTrace) {
            $line .= "\nStack trace:\n";
            $line .= "#0 /var/www/html/vendor/craftcms/cms/src/base/Component.php(112): craft\\base\\Component->init()\n";
            $line .= "#1 /var/www/html/vendor/craftcms/cms/src/web/Application.php(348): craft\\base\\Application->bootstrap()\n";
            $line .= "#2 /var/www/html/modules/site/controllers/SiteController.php(87): craft\\web\\Application->handleRequest()\n";
            $line .= "#3 /var/www/html/web/index.php(21): yii\\base\\Application->run()\n";
        }

        $line .= "\n";

        file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }

    private function color(string $level): string
    {
        return match ($level) {
            'error' => 'red',
            'warning' => 'yellow',
            default => 'cyan',
        };
    }
}
