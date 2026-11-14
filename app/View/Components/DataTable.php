<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DataTable extends Component
{
    /**
     * @param  list<array{key: string, label: string, sortable?: bool, align?: string}>  $columns
     * @param  string  $caption  Describes the table to screen readers.
     * @param  bool  $isEmpty  Show the "empty" slot instead of the table.
     */
    public function __construct(
        public array $columns,
        public string $caption,
        public bool $isEmpty = false,
        public ?string $sort = null,
        public string $direction = 'asc',
    ) {
    }

    /**
     * @return list<array{label: string, sortable: bool, url: string, aria: string|null, arrow: string, class: string, hint: string}>
     */
    public function headers(): array
    {
        return array_map(function (array $column): array {
            $sortable = $column['sortable'] ?? false;
            $current = $sortable && $this->sort === $column['key'];
            $nextDirection = $current && $this->direction === 'asc' ? 'desc' : 'asc';

            return [
                'label' => $column['label'],
                'sortable' => $sortable,
                'url' => $sortable
                    ? request()->fullUrlWithQuery(['sort' => $column['key'], 'direction' => $nextDirection, 'page' => null])
                    : '',
                'aria' => $current ? ($this->direction === 'asc' ? 'ascending' : 'descending') : null,
                'arrow' => $current ? ($this->direction === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevrons-up-down',
                'class' => ($column['align'] ?? 'start') === 'end' ? 'num' : '',
                'hint' => 'Sort by '.mb_strtolower($column['label']),
            ];
        }, $this->columns);
    }

    public function render(): View
    {
        return view('components.data-table');
    }
}
