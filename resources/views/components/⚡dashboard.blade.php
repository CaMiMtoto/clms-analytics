<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MvPendingApplication;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $selectedSector = '';
    public string $selectedStatus = '';
    public string $search = '';

    #[\Livewire\Attributes\Url(except: '')]
    public string $sortBy = 'days_pending';
    #[\Livewire\Attributes\Url(except: '')]
    public string $sortDirection = 'desc';

    public array $kpis = [];
    public array $statusData = [];
    public array $sectorData = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function sort($column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['selectedSector', 'selectedStatus', 'search'])) {
            $this->resetPage();
            $this->loadData();
            // Pass the updated data to the JavaScript event
            $this->dispatch('chartsUpdated', statusData: $this->statusData, sectorData: $this->sectorData);
        }
    }

    public function loadData(): void
    {
        // 1. Sector Data for Bar Chart
        $sectorsQuery = MvPendingApplication::select('regulated_sector', DB::raw('count(*) as total'))
            ->whereNotNull('regulated_sector')
            ->groupBy('regulated_sector')
            ->orderBy('total', 'desc');

        if ($this->selectedStatus) {
            $sectorsQuery->where('status_name', $this->selectedStatus);
        }
        if ($this->search) {
            $sectorsQuery->where(function ($q) {
                $q->where('operator_name', 'like', '%' . $this->search . '%')
                    ->orWhere('doc_number', 'like', '%' . $this->search . '%');
            });
        }

        $sectors = $sectorsQuery->get();

        $this->sectorData = [
            'labels' => $sectors->pluck('regulated_sector')->map(fn($s) => ucfirst(strtolower($s)))->toArray(),
            'counts' => $sectors->pluck('total')->toArray(),
        ];

        // 2. Health Status Data for Donut Chart & KPIs
        $baseKpiQuery = MvPendingApplication::query();

        if ($this->selectedSector) {
            $baseKpiQuery->where('regulated_sector', $this->selectedSector);
        }
        if ($this->selectedStatus) {
            $baseKpiQuery->where('status_name', $this->selectedStatus);
        }
        if ($this->search) {
            $baseKpiQuery->where(function ($q) {
                $q->where('operator_name', 'like', '%' . $this->search . '%')
                    ->orWhere('doc_number', 'like', '%' . $this->search . '%');
            });
        }

        $totalApplications = (clone $baseKpiQuery)->count();

        $onTrack = (clone $baseKpiQuery)->where('days_pending', '<=', 20)->count();
        $delayed = (clone $baseKpiQuery)->where('days_pending', '>', 20)->where('days_pending', '<=', 90)->count();
        $significant = (clone $baseKpiQuery)->where('days_pending', '>', 90)->where('days_pending', '<=', 180)->count();
        $critical = (clone $baseKpiQuery)->where('days_pending', '>', 180)->count();

        $this->statusData = [
            [
                'label' => 'On Track',
                'count' => $onTrack,
                'percentage' => $totalApplications > 0 ? round(($onTrack / $totalApplications) * 100) : 0,
                'color' => '#10b981',
                'tailwind' => 'emerald-500'
            ],
            [
                'label' => 'Delayed',
                'count' => $delayed,
                'percentage' => $totalApplications > 0 ? round(($delayed / $totalApplications) * 100) : 0,
                'color' => '#f59e0b',
                'tailwind' => 'amber-500'
            ],
            [
                'label' => 'Significant',
                'count' => $significant,
                'percentage' => $totalApplications > 0 ? round(($significant / $totalApplications) * 100) : 0,
                'color' => '#f97316',
                'tailwind' => 'orange-500'
            ],
            [
                'label' => 'Critical',
                'count' => $critical,
                'percentage' => $totalApplications > 0 ? round(($critical / $totalApplications) * 100) : 0,
                'color' => '#f43f5e',
                'tailwind' => 'rose-500'
            ],
        ];

        // 3. KPIs
        $this->kpis = [
            [
                'label' => 'Total Pending',
                'value' => $totalApplications,
                'description' => 'Active applications',
                'icon' => 'lucide-layers',
                'color' => 'blue',
            ],
            [
                'label' => 'On Track',
                'value' => $onTrack,
                'description' => '0-20 days',
                'icon' => 'lucide-check-circle-2',
                'color' => 'emerald',
            ],
            [
                'label' => 'Delayed',
                'value' => $delayed,
                'description' => '21-90 days',
                'icon' => 'lucide-clock-3',
                'color' => 'amber',
            ],
            [
                'label' => 'Significant',
                'value' => $significant,
                'description' => '91-180 days',
                'icon' => 'lucide-alert-circle',
                'color' => 'orange',
            ],
            [
                'label' => 'Critical',
                'value' => $critical,
                'description' => '180+ days',
                'icon' => 'lucide-alert-triangle',
                'color' => 'rose',
            ],
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function pendingApplications(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = MvPendingApplication::query();

        if ($this->selectedSector) {
            $query->where('regulated_sector', $this->selectedSector);
        }
        if ($this->selectedStatus) {
            $query->where('status_name', $this->selectedStatus);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('operator_name', 'like', '%' . $this->search . '%')
                    ->orWhere('doc_number', 'like', '%' . $this->search . '%');
            });
        }

        return $query->orderBy($this->sortBy, $this->sortDirection)->paginate(10);
    }

    #[\Livewire\Attributes\Computed]
    public function sectors(): array
    {
        return MvPendingApplication::select('regulated_sector')
            ->whereNotNull('regulated_sector')
            ->distinct()
            ->pluck('regulated_sector')
            ->toArray();
    }

    #[\Livewire\Attributes\Computed]
    public function statuses(): array
    {
        return MvPendingApplication::select('status_name')
            ->whereNotNull('status_name')
            ->distinct()
            ->pluck('status_name')
            ->toArray();
    }
};
?>

<div class="p-6">
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item wire:navigate href="{{ route('admin.dashboard') }}" separator="slash">Dashboard
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item separator="slash">Analytics</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight mb-1">Global Dashboard</h1>
            <p class="text-slate-400 text-sm">Real-time application tracking and sector performance analytics.</p>
        </div>

    </div>

    <div class="flex flex-wrap gap-4 my-4 items-end">
        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Search</label>
            <flux:input wire:model.live="search" placeholder="Operator or doc..." icon="search" class="w-64"/>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sector</label>
            <select wire:model.live="selectedSector"
                    class="bg-white border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500/20 min-w-37.5 ">
                <option value="">All Sectors</option>
                @foreach($this->sectors as $sector)
                    <option value="{{ $sector }}">{{ ucfirst(strtolower($sector)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</label>
            <select wire:model.live="selectedStatus"
                    class="bg-white border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500/20 min-w-37.5 ">
                <option value="">All Statuses</option>
                @foreach($this->statuses as $status)
                    <option value="{{ $status }}">{{ ucfirst(strtolower($status)) }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="$set('selectedSector', ''); $set('selectedStatus', ''); $set('search', '')"
                class="h-10.5 px-4 bg-white border border-slate-200 rounded-lg text-primary font-bold text-sm  flex items-center gap-2 transition-all  group">
            <x-lucide-rotate-ccw class="w-4 h-4 group-hover:-rotate-45 transition-transform"/>
            Reset
        </button>
        <div class="hidden items-center justify-center h-10.5 px-4" wire:loading.class="flex"
             wire:loading.class.remove="hidden">
            <x-lucide-loader-circle class="w-5 h-5 text-slate-400 animate-spin"/>
        </div>
    </div>

    {{-- Redesigned KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-10">
        @foreach($kpis as $kpi)
            <div @class([
                'relative overflow-hidden p-4 rounded-2xl border bg-white transition-all hover:shadow-md group',
                'border-blue-100 hover:border-blue-200' => $kpi['color'] === 'blue',
                'border-emerald-100 hover:border-emerald-200' => $kpi['color'] === 'emerald',
                'border-amber-100 hover:border-amber-200' => $kpi['color'] === 'amber',
                'border-orange-100 hover:border-orange-200' => $kpi['color'] === 'orange',
                'border-rose-100 hover:border-rose-200' => $kpi['color'] === 'rose',
            ])>
                <div class="flex justify-between items-start mb-4">
                    <div @class([
                        'p-2.5 rounded-xl transition-colors',
                        'bg-accent/10 text-accent group-hover:bg-blue-100' => $kpi['color'] === 'blue',
                        'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100' => $kpi['color'] === 'emerald',
                        'bg-amber-50 text-amber-600 group-hover:bg-amber-100' => $kpi['color'] === 'amber',
                        'bg-orange-50 text-orange-600 group-hover:bg-orange-100' => $kpi['color'] === 'orange',
                        'bg-rose-50 text-rose-600 group-hover:bg-rose-100' => $kpi['color'] === 'rose',
                    ])>
                        <x-dynamic-component :component="$kpi['icon']" class="w-6 h-6"/>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ $kpi['label'] }}</h3>
                    <div class="flex items-baseline gap-2">
                        <span
                            class="text-3xl font-bold text-slate-900 tracking-tight">{{ number_format($kpi['value']) }}</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 font-medium">{{ $kpi['description'] }}</p>
                </div>

                <div @class([
                    'absolute -right-4 -bottom-4 w-24 h-24 opacity-[0.03] transition-transform group-hover:scale-110',
                    'text-blue-600' => $kpi['color'] === 'blue',
                    'text-emerald-600' => $kpi['color'] === 'emerald',
                    'text-amber-600' => $kpi['color'] === 'amber',
                    'text-orange-600' => $kpi['color'] === 'orange',
                    'text-rose-600' => $kpi['color'] === 'rose',
                ])>
                    <x-dynamic-component :component="$kpi['icon']" class="w-full h-full"/>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
        <!-- Sector Chart -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-8 ">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Applications by Sector</h3>
                    <p class="text-sm text-slate-500 mt-1">Volume distribution across departments</p>
                </div>
            </div>

            <div class="relative h-72">
                <canvas id="sectorChart" wire:ignore></canvas>
            </div>
        </div>

        <!-- Health Status Donut -->
        <div class="bg-white rounded-2xl border border-slate-200 p-8">
            <div class="mb-8">
                <h3 class="text-xl font-bold text-slate-900">Health Status</h3>
                <p class="text-sm text-slate-500 mt-1">Application lifecycle metrics</p>
            </div>

            <div class="relative w-56 h-56 mx-auto mb-10">
                <canvas id="healthChart" wire:ignore></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span
                        class="text-4xl font-black text-slate-900 leading-none">{{ number_format(collect($statusData)->sum('count')) }}</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Total</span>
                </div>
            </div>

            <div class="space-y-4">
                @foreach($statusData as $status)
                    <div class="flex justify-between items-center group cursor-default">
                        <div class="flex items-center gap-3">
                            <div @class([
                                'w-3 h-3 rounded-full shadow-sm',
                                'bg-emerald-500 shadow-emerald-100' => $status['tailwind'] === 'emerald-500',
                                'bg-amber-500 shadow-amber-100' => $status['tailwind'] === 'amber-500',
                                'bg-orange-500 shadow-orange-100' => $status['tailwind'] === 'orange-500',
                                'bg-rose-500 shadow-rose-100' => $status['tailwind'] === 'rose-500',
                            ])></div>
                            <span
                                class="text-sm font-bold text-slate-600 group-hover:text-slate-900 transition-colors">{{ $status['label'] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="text-xs font-medium text-slate-400">{{ number_format($status['count']) }} items</span>
                            <span class="text-sm font-black text-slate-900 min-w-[3rem] text-right">{{ $status['percentage'] }}%</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div
            class="px-8 py-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Application Delay Registry</h3>
                <p class="text-sm text-slate-500 mt-0.5">Detailed view of pending applications with aging metrics.</p>
            </div>
        </div>


        <flux:table :paginate="$this->pendingApplications">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'doc_number'" :direction="$sortDirection"
                                   wire:click="sort('doc_number')">
                    <span class="px-3">Doc Number</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'operator_name'" :direction="$sortDirection"
                                   wire:click="sort('operator_name')">
                    <span class="px-3">Operator</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'license_type'" :direction="$sortDirection"
                                   wire:click="sort('license_type')">
                    <span class="px-3">License</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'regulated_sector'" :direction="$sortDirection"
                                   wire:click="sort('regulated_sector')">
                    <span class="px-3">Sector</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'unit_name'" :direction="$sortDirection"
                                   wire:click="sort('unit_name')">
                    <span class="px-3">Unit</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'department_name'" :direction="$sortDirection"
                                   wire:click="sort('department_name')">
                    <span class="px-3">Department</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status_name'" :direction="$sortDirection"
                                   wire:click="sort('status_name')">
                    <span class="px-3">Status</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'submission_date'" :direction="$sortDirection"
                                   wire:click="sort('submission_date')">
                    <span class="px-3">Submitted At</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'days_pending'" :direction="$sortDirection"
                                   wire:click="sort('days_pending')">
                    <span class="px-3">Pending Days</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="px-3">Aging Bucket
                    </span>
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->pendingApplications as $app)
                    <flux:table.row>
                        <flux:table.cell>
                            <span class="px-3">
                                {{$app->doc_number??'-'}}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>{{ ucfirst(strtolower($app->operator_name)) }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst(strtolower($app->license_type)) }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst(strtolower($app->regulated_sector)) }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst(strtolower($app->unit_name)) }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst(strtolower($app->department_name)) }}</flux:table.cell>
                        <flux:table.cell>
                             <span @class([
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border',
                                    'bg-rose-50 text-rose-700 border-rose-100' => $app->days_pending > 180,
                                    'bg-orange-50 text-orange-700 border-orange-100' => $app->days_pending > 90 && $app->days_pending <= 180,
                                    'bg-amber-50 text-amber-700 border-amber-100' => $app->days_pending > 20 && $app->days_pending <= 90,
                                    'bg-emerald-50 text-emerald-700 border-emerald-100' => $app->days_pending <= 20,
                                ])>
                                {{ $app->status_name }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-slate-500">{{ $app->submission_date }}</flux:table.cell>
                        <flux:table.cell>
                             <span @class([
                                'font-bold',
                                'text-rose-600' => $app->days_pending > 180,
                                'text-orange-600' => $app->days_pending > 90 && $app->days_pending <= 180,
                                'text-slate-700' => $app->days_pending <= 90,
                            ])>
                                {{ $app->days_pending }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span
                                class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{{ $app->age_bucket }}</span>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach

            </flux:table.rows>
        </flux:table>

    </div>
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endassets

@script
<script>
    let sectorChart, healthChart;

    function initCharts(newStatusData = null, newSectorData = null) {
        // Use the new data if provided, otherwise use the initial data from Livewire's first render
        const currentSectorData = newSectorData || @js($sectorData);
        const currentStatusData = newStatusData || @js($statusData);

        const sectorCtx = document.getElementById('sectorChart').getContext('2d');

        const sectorGradient = sectorCtx.createLinearGradient(0, 0, 0, 300);
        sectorGradient.addColorStop(0, '#00487F');
        sectorGradient.addColorStop(1, '#004C85');

        if (sectorChart) sectorChart.destroy();
        sectorChart = new Chart(sectorCtx, {
            type: 'bar',
            data: {
                labels: currentSectorData.labels,
                datasets: [{
                    label: 'Volume Count',
                    data: currentSectorData.counts,
                    backgroundColor: sectorGradient,
                    borderRadius: 8,
                    barThickness: 32,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        displayColors: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {color: '#f1f5f9', drawBorder: false},
                        ticks: {color: '#94a3b8', font: {size: 11, weight: '600'}}
                    },
                    x: {
                        grid: {display: false},
                        ticks: {color: '#64748b', font: {size: 11, weight: '700'}}
                    }
                }
            }
        });

        const healthCtx = document.getElementById('healthChart').getContext('2d');

        if (healthChart) healthChart.destroy();
        healthChart = new Chart(healthCtx, {
            type: 'doughnut',
            data: {
                labels: currentStatusData.map(s => s.label),
                datasets: [{
                    data: currentStatusData.map(s => s.count),
                    backgroundColor: currentStatusData.map(s => s.color),
                    borderWidth: 0,
                    hoverOffset: 4,
                    cutout: '82%',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        callbacks: {
                            label: function (context) {
                                const item = currentStatusData[context.dataIndex];
                                return `${item.label}: ${item.count} items (${item.percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Initial chart render
    initCharts();

    // Listen for Livewire updates and re-render charts with new data
    Livewire.on('chartsUpdated', ({ statusData, sectorData }) => {
        initCharts(statusData, sectorData);
    });
</script>
@endscript
