<?php

declare(strict_types=1);

return [
    'attributes' => [
        'name' => 'nome',
        'active' => 'ativo',
        'settlement_days' => 'prazo de recebimento',
        'fee_percent' => 'taxa percentual',
        'fee_fixed' => 'taxa fixa',
    ],

    'validation' => [
        'name.required' => 'O nome do terminal é obrigatório.',
        'name.string' => 'O nome do terminal deve ser um texto.',
        'name.max' => 'O nome do terminal não pode ter mais de :max caracteres.',
        'active.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        'settlement_days.required' => 'Informe o prazo de recebimento em dias.',
        'settlement_days.integer' => 'O prazo de recebimento deve ser um número inteiro de dias.',
        'settlement_days.min' => 'O prazo de recebimento não pode ser negativo.',
        'settlement_days.max' => 'O prazo de recebimento não pode ser maior que :max dias.',
        'fee_percent.required' => 'Informe a taxa percentual.',
        'fee_percent.numeric' => 'A taxa percentual deve ser um número.',
        'fee_percent.min' => 'A taxa percentual não pode ser negativa.',
        'fee_percent.max' => 'A taxa percentual não pode ser maior que :max%.',
        'fee_fixed.integer' => 'A taxa fixa deve ser um valor inteiro em centavos.',
        'fee_fixed.min' => 'A taxa fixa não pode ser negativa.',
        'fee_fixed.max' => 'A taxa fixa não pode ser maior que :max centavos.',
    ],
];
