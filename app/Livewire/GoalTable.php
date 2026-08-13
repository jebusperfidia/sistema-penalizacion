<?php

namespace App\Livewire;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class GoalTable extends PowerGridComponent
{
    public string $tableName = 'goals-table';

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return DB::table('goals')
            ->leftJoin('categories', 'goals.category_id', '=', 'categories.id')
            ->leftJoin('time_logs', 'goals.id', '=', 'time_logs.goal_id')
            ->select([
                'goals.id',
                'goals.titulo',
                'goals.fecha_inicio',
                'goals.estado',
                'goals.created_at',
                'categories.nombre as categoria_nombre',
                DB::raw('COALESCE(SUM(time_logs.horas_invertidas), 0) as total_horas')
            ])
            ->groupBy('goals.id', 'goals.titulo', 'goals.fecha_inicio', 'goals.estado', 'goals.created_at', 'categories.nombre');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('titulo')
            ->add('categoria_nombre', fn($model) => $model->categoria_nombre ?? 'Sin categoría')
            ->add('fecha_inicio_formatted', fn($model) => Carbon::parse($model->fecha_inicio)->format('d/m/Y'))
            ->add('total_horas', fn($model) => number_format($model->total_horas, 1) . ' hrs')
            ->add('estado_label', fn($model) => $model->estado ? 'Completada' : 'En Curso');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Título', 'titulo')
                ->sortable()
                ->searchable(),

            Column::make('Materia / Categoría', 'categoria_nombre', 'categories.nombre')
                ->sortable()
                ->searchable(),

            Column::make('Fecha Inicio', 'fecha_inicio_formatted', 'fecha_inicio')
                ->sortable(),

            Column::make('Horas Invertidas', 'total_horas'),

            Column::make('Estado', 'estado_label', 'estado')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
