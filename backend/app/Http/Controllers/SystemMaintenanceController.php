<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Runs deployment chores (migrations, seeders, cache clear + rebuild) from the
 * Settings page for servers without shell access. Logged-in users with the
 * settings.update permission only; POST so it cannot fire from a plain link.
 */
class SystemMaintenanceController extends Controller
{
    /** @var array<string, array<int, array{0: string, 1: array<string, mixed>}>> */
    private const TASKS = [
        'migrate' => [['migrate', ['--force' => true]]],
        'seed' => [['db:seed', ['--force' => true]]],
        'optimize' => [['optimize:clear', []], ['optimize', []]],
        'all' => [['migrate', ['--force' => true]], ['db:seed', ['--force' => true]], ['optimize:clear', []], ['optimize', []]],
    ];

    public function __invoke(Request $request, string $task): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);
        abort_unless(array_key_exists($task, self::TASKS), 404);

        set_time_limit(300);
        $output = [];
        $failed = false;

        foreach (self::TASKS[$task] as [$command, $parameters]) {
            try {
                $exitCode = Artisan::call($command, $parameters);
                $output[] = "$ php artisan {$command}\n".trim(Artisan::output());
                if ($exitCode !== 0) {
                    $failed = true;
                    break;
                }
            } catch (Throwable $exception) {
                $output[] = "$ php artisan {$command}\nFAILED: {$exception->getMessage()}";
                $failed = true;
                break;
            }
        }

        if (! $failed && in_array($task, ['optimize', 'all'], true) && function_exists('opcache_reset')) {
            $output[] = opcache_reset() ? 'PHP OPcache reset.' : 'PHP OPcache could not be reset.';
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'system.maintenance.'.$task,
            'properties' => ['failed' => $failed],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('settings.index')
            ->with($failed ? 'maintenance_error' : 'success', $failed ? 'Maintenance task stopped with an error. See the output below.' : 'Maintenance task completed successfully.')
            ->with('maintenance_output', implode("\n\n", $output));
    }
}
