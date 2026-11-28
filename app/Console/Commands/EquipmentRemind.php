<?php

namespace App\Console\Commands;

use App\Services\Equipment\Reminders;
use Illuminate\Console\Command;

class EquipmentRemind extends Command
{
    protected $signature = 'equipment:remind';

    protected $description = 'Remind borrowers of equipment that is overdue (first after the grace period, then daily)';

    public function handle(Reminders $reminders): int
    {
        $r = $reminders->run();
        $this->info(sprintf('Overdue loans checked: %d. Reminders sent: %d. Failed: %d.', $r['checked'], $r['sent'], $r['failed']));

        return self::SUCCESS;
    }
}
