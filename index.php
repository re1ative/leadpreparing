<?php

/**
 *   Условие
 *   Условно дан массив лидов из формы:
 *
 *   $leads = [
 *       ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'email' => 'ivan@mail.ru'],
 *       ['name' => 'Мария', 'phone' => '89995554433', 'email' => 'maria@mail.ru'],
 *       ['name' => 'Иван', 'phone' => '+7 999 111 22 33', 'email' => 'ivan2@mail.ru'], 
 *      ['name' => '', 'phone' => '89990000000', 'email' => 'no_name@mail.ru'], 
 *   ];
 *
 *   Нужно:
 *
 *   1.	Нормализовать телефон к формату 7XXXXXXXXXX.
 *   2.	Проверить обязательные поля: name, phone.
 *   3.	Убрать дубли по телефону.
 *   4.	Подготовить массив fields для crm.lead.add.
 *   5.	Вернуть:
 *   ready — что можно отправлять в Bitrix24,
 *   errors — что отклонено и почему.
 */

/**
 * Утилиты для обработки номера телефона
 */
class PhoneUtils
{
    /**
     * Нормализует номер телефона к виду 7XXXXXXXXXX. Корректно только для российских номеров.
     * @param string $phone Номер телефона
     * @throws Exception
     * @return string
     */
    public static function normalize(string $phone): string
    {

        $cleaned = preg_filter('/[\D]*/m', "", $phone);

        if (empty($cleaned)) {
            throw new Exception("В номере телефона отсутствуют цифры");
        }

        if (strlen($cleaned) == 10) {
            $normalized = sprintf("7%s", $cleaned);
        } elseif (strlen($cleaned) == 11) {
            $normalized = preg_replace('/^[^7]/m', '7', $cleaned);
        } else {
            throw new Exception("Некорректный номер телефона");
        }

        return $normalized;
    }
}

/**
 * Утилиты для обработки лидов
 */
class LeadUtils
{
    /**
     * Подготавливает лиды к отправке в B24. В результате в errors ключами являются индексы элементов из $leads,
     * а ready содержит массив параметров для вызова crm.lead.add: 
     * [
     *  ['fields' = [...]],
     *  ...,
     *  ['fields' = [...]]
     * ]
     * @param array $leads
     * @return array{ready: array, errors: array}
     */
    public static function prepareForSending(array $leads): array
    {
        $ready = [];
        $errors = [];

        $seen = [];

        foreach ($leads as $key => $elem) {
            try {
                if (empty($elem['name'])) {
                    throw new Exception('Не указано имя');
                }

                if (empty($elem['phone'])) {
                    throw new Exception('Не указан номер телефона');
                }

                $normalizedPhone = PhoneUtils::normalize($elem['phone']);

                if (isset($seen[$normalizedPhone])) {
                    throw new Exception(sprintf("Лид с номером телефона %s ранее был уже обработан", $normalizedPhone));
                }

                $seen[$normalizedPhone] = true;

                $fields = [
                    'fields' => [
                        'NAME' => $elem['name'],
                        'PHONE' => [
                            [
                                'VALUE' => $normalizedPhone,
                                'VALUE_TYPE' => 'WORK'
                            ]
                        ]
                    ]
                ];

                if (isset($elem['email'])) {
                    $fields['fields']['EMAIL'] = [
                        [
                            'VALUE' => $elem['email'],
                            'VALUE_TYPE' => 'WORK'
                        ]
                    ];
                }

                $ready[] = $fields;
            } catch (\Throwable $th) {
                $errors[$key] = $th->getMessage();
            }
        }

        return [
            'ready' => $ready,
            'errors' => $errors
        ];
    }
}

$leads = [
    ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'email' => 'ivan@mail.ru'],
    ['name' => 'Мария', 'phone' => '8(999) 111-22-33', 'email' => 'maria@mail.ru'],
    ['name' => 'Иван', 'phone' => 'eeee', 'email' => 'ivan2@mail.ru'],
    ['name' => '', 'phone' => '89990000000', 'email' => 'no_name@mail.ru'],
    ['name' => 'Вещий Олег', 'phone' => '', 'email' => 'knyaz@mail.ru'],
    ['name' => 'Борис', 'phone' => '+7999 333-22-33', 'email' => 'boris@mail.ru'],
];
$result = LeadUtils::prepareForSending($leads);
var_dump($result);
