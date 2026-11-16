<?php

declare(strict_types=1);

return [
    'attributes' => [
        'code' => 'código',
        'label' => 'nome',
        'type' => 'tipo',
        'value' => 'valor',
        'active' => 'ativo',
        'starts_at' => 'início',
        'ends_at' => 'término',
        'max_uses' => 'limite de usos',
        'gross' => 'total bruto',
    ],

    'validation' => [
        'code.required' => 'O código do cupom é obrigatório.',
        'code.string' => 'O código do cupom deve ser um texto.',
        'code.max' => 'O código do cupom não pode ter mais de :max caracteres.',
        'code.unique' => 'Já existe um cupom com este código.',
        'label.string' => 'O nome do cupom deve ser um texto.',
        'label.max' => 'O nome do cupom não pode ter mais de :max caracteres.',
        'type.required' => 'Informe o tipo do cupom.',
        'type.enum' => 'O tipo do cupom deve ser percentual ou valor fixo.',
        'value.required' => 'Informe o valor do cupom.',
        'value.integer' => 'O valor do cupom deve ser um número inteiro.',
        'value.min' => 'O valor do cupom deve ser de pelo menos :min.',
        'active.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        'starts_at.date' => 'A data de início do cupom é inválida.',
        'ends_at.date' => 'A data de término do cupom é inválida.',
        'ends_at.after_or_equal' => 'A data de término deve ser igual ou posterior ao início.',
        'max_uses.integer' => 'O limite de usos deve ser um número inteiro.',
        'max_uses.min' => 'O limite de usos deve ser de pelo menos :min.',
        'gross.integer' => 'O total bruto deve ser um inteiro em centavos.',
        'gross.min' => 'O total bruto não pode ser negativo.',
    ],

    'errors' => [
        'not_found' => 'Cupom não encontrado.',
        'inactive' => 'Este cupom está inativo.',
        'expired' => 'Este cupom está expirado.',
        'not_started' => 'Este cupom ainda não está válido.',
        'max_uses' => 'Este cupom atingiu o limite de usos.',
    ],
];
