<?php

namespace App\Console\Commands;

use App\Models\Queue;
use Illuminate\Console\Command;

class CloseDailyAppointmentQueues extends Command
{
    protected $signature = 'queues:close-daily-appointment';

    protected $description = 'Close still-open queues for appointment service (service 1) and doctor id > 1 so tokens renew daily.';

    public function handle(): int
    {
        $count = Queue::dailyAppointmentToClose()->active()->update([
            'status' => 'discontinued',
            'ended_at' => now(),
        ]);

        if ($count > 0) {
            $this->info("Closed {$count} daily appointment queue(s).");
        }

        return self::SUCCESS;
    }
}
