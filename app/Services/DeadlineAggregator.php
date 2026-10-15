<?php

namespace App\Services;

use App\Enums\IndexType;
use App\Models\Contract;
use App\Models\Deadline;
use App\Models\InsurancePolicy;
use App\Models\Lease;
use App\Models\Loan;
use App\Models\MaintenanceTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sammelt alle Fristen aus den Modulen (Versicherungen, Vertraege,
 * Mietvertraege, Kredite, Wartung, manuelle Fristen).
 */
class DeadlineAggregator
{
    /**
     * @return Collection<int, array{date: Carbon, title: string, category: string, property: ?string, property_id: ?int, overdue: bool, url: ?string}>
     */
    public function upcoming(int $days = 90, bool $includeOverdue = true): Collection
    {
        $from = today();
        $to = today()->addDays($days);
        $items = collect();

        $add = function (?Carbon $date, string $title, string $category, $property, ?string $url) use ($items) {
            if ($date === null) {
                return;
            }
            $items->push([
                'date' => $date->copy(),
                'title' => $title,
                'category' => $category,
                'property' => $property?->name,
                'property_id' => $property?->id,
                'overdue' => $date->lt(today()),
                'url' => $url,
            ]);
        };

        foreach (InsurancePolicy::with('property')->get() as $p) {
            $url = $this->url('insurance-policies', $p->id);
            $label = $p->line?->getLabel().' '.$p->insurer;
            $add($p->noticeDeadline(), "Kündigungsfrist: $label", 'Versicherung', $p->property, $url);
            $add($p->nextExpiry(), ($p->auto_renews ? 'Verlängerung: ' : 'Ablauf: ').$label, 'Versicherung', $p->property, $url);
        }

        foreach (Contract::with('property')->get() as $c) {
            $url = $this->url('contracts', $c->id);
            $label = $c->type?->getLabel().' '.$c->provider;
            $add($c->noticeDeadline(), "Kündigungsfrist: $label", 'Vertrag', $c->property, $url);
            $add($c->nextExpiry(), ($c->auto_renews ? 'Verlängerung: ' : 'Ablauf: ').$label, 'Vertrag', $c->property, $url);
        }

        foreach (Lease::with(['property', 'tenant'])->get() as $l) {
            $url = $this->url('leases', $l->id);
            $who = $l->tenant?->display_name;
            if ($l->end_date !== null && $l->end_date->gte(today()->subDays(30))) {
                $add($l->end_date, ($l->is_fixed_term ? 'Befristung endet: ' : 'Mietende: ').$who, 'Mietvertrag', $l->property, $url);
            }
            if ($l->index_type !== null && $l->index_type !== IndexType::None && $l->isActive()) {
                $add($this->nextIndexCheck($l), "Wertsicherung prüfen: $who", 'Mietvertrag', $l->property, $url);
            }
        }

        foreach (Loan::with('property')->whereNotNull('fixed_until')->get() as $loan) {
            $add($loan->fixed_until, 'Zinsbindung endet: '.$loan->bank, 'Kredit', $loan->property, $this->url('loans', $loan->id));
        }

        foreach (MaintenanceTask::with('property')->whereNotNull('next_due_at')->get() as $m) {
            $add($m->next_due_at, 'Wartung: '.$m->title, 'Wartung', $m->property, $this->url('maintenance-tasks', $m->id));
        }

        foreach (Deadline::with('property')->whereNull('done_at')->get() as $d) {
            $add($d->due_date, $d->title, 'Frist', $d->property, $this->url('deadlines', $d->id));
        }

        return $items
            ->filter(fn ($i) => $i['date']->lte($to) && ($includeOverdue ? true : $i['date']->gte($from)))
            // Abgelaufene automatische Fristen nur, wenn sie hoechstens 30 Tage zurueckliegen.
            ->filter(fn ($i) => $i['category'] === 'Frist' || $i['category'] === 'Wartung' || $i['date']->gte(today()->subDays(30)))
            ->sortBy(fn ($i) => $i['date']->timestamp)
            ->values();
    }

    /** Jaehrliche Pruefung ab letzter Anpassung bzw. Basismonat bzw. Mietbeginn. */
    public function nextIndexCheck(Lease $lease): Carbon
    {
        $anchor = ($lease->index_last_adjusted_at ?? $lease->index_base_month ?? $lease->start_date)->copy();
        $next = $anchor->copy()->addYearNoOverflow();
        while ($next->lt(today()->subDays(30))) {
            $next->addYearNoOverflow();
        }

        return $next;
    }

    private function url(string $resource, int $id): ?string
    {
        try {
            return url("/admin/$resource/$id/edit");
        } catch (\Throwable) {
            return null;
        }
    }
}
