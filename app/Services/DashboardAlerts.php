<?php

namespace App\Services;

use App\Enums\AnimalListFilter;
use App\Enums\DiaryDueFilter;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\DwaLicence;
use App\Models\StockItem;

class DashboardAlerts
{
    /**
     * @return list<DashboardAlert>
     */
    public function all(): array
    {
        return [
            $this->quarantine(),
            $this->overdueMedical(),
            $this->overdueTasks(),
            $this->tasksDueToday(),
            $this->citesInCare(),
            $this->missingArticleTen(),
            $this->dwaInCare(),
            $this->dwaRenewals(),
            $this->lowStock(),
        ];
    }

    public function lowStock(): DashboardAlert
    {
        return new DashboardAlert(
            key: 'low_stock',
            label: 'Low stock',
            description: 'Food and medical items at or below their reorder level.',
            count: StockItem::query()->lowStock()->count(),
            url: route('stock.index', ['filter' => 'low']),
            tone: 'quarantine',
        );
    }

    public function dwaRenewals(): DashboardAlert
    {
        return new DashboardAlert(
            key: 'dwa_renewals',
            label: 'DWA licences to renew',
            description: 'Dangerous Wild Animals licences expired or expiring within '.DwaLicence::RENEWAL_REMINDER_DAYS.' days.',
            count: DwaLicence::query()->needsRenewal()->count(),
            url: route('compliance.dwa.index', ['filter' => 'renewal']),
            tone: 'dwa',
        );
    }

    public function quarantine(): DashboardAlert
    {
        return $this->animalAlert(
            AnimalListFilter::Quarantine,
            'Animals whose latest movement is quarantine.',
            'quarantine',
        );
    }

    public function overdueMedical(): DashboardAlert
    {
        return $this->animalAlert(
            AnimalListFilter::MedicalOverdue,
            'Animals with a vaccination, test or treatment past its due date and not given.',
            'danger',
        );
    }

    public function citesInCare(): DashboardAlert
    {
        return $this->animalAlert(
            AnimalListFilter::Cites,
            'Animals in care with a CITES Annex or reference to keep paperwork for.',
            'cites',
        );
    }

    public function missingArticleTen(): DashboardAlert
    {
        return $this->animalAlert(
            AnimalListFilter::MissingArticleTen,
            'Annex A animals in care with no Article 10 certificate on file.',
            'danger',
        );
    }

    public function dwaInCare(): DashboardAlert
    {
        return $this->animalAlert(
            AnimalListFilter::Dwa,
            'Animals in care covered by a Dangerous Wild Animals licence.',
            'dwa',
        );
    }

    public function overdueTasks(): DashboardAlert
    {
        return $this->taskAlert(DiaryDueFilter::Overdue, 'Tasks overdue', 'Open diary tasks with a due date before today.', 'danger');
    }

    public function tasksDueToday(): DashboardAlert
    {
        return $this->taskAlert(DiaryDueFilter::Today, 'Tasks due today', 'Open diary tasks due today.', 'neutral');
    }

    private function animalAlert(AnimalListFilter $filter, string $description, string $tone): DashboardAlert
    {
        return new DashboardAlert(
            key: $filter->value,
            label: $filter->label(),
            description: $description,
            count: $filter->apply(Animal::query())->count(),
            url: route('animals.index', ['filter' => $filter->value]),
            tone: $tone,
        );
    }

    private function taskAlert(DiaryDueFilter $filter, string $label, string $description, string $tone): DashboardAlert
    {
        return new DashboardAlert(
            key: 'tasks_'.$filter->value,
            label: $label,
            description: $description,
            count: $filter->apply(DiaryTask::query())->count(),
            url: route('diary.index', ['due' => $filter->value]),
            tone: $tone,
        );
    }
}
