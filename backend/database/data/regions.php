<?php

/*
| Structured regions for countries with state/district pickers.
| India: 28 states + 8 union territories, all 14 Kerala districts.
| Russia: the 83 federal subjects in the Russian constitution as recognised
| internationally. Admins can add more regions/districts from the lookup screen.
*/

$indiaStates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh',
    'Uttarakhand', 'West Bengal',
];

$indiaUts = [
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
];

$russia = [
    'republic' => [
        'Adygea', 'Altai Republic', 'Bashkortostan', 'Buryatia', 'Chechnya', 'Chuvashia',
        'Dagestan', 'Ingushetia', 'Kabardino-Balkaria', 'Kalmykia', 'Karachay-Cherkessia',
        'Karelia', 'Khakassia', 'Komi', 'Mari El', 'Mordovia', 'North Ossetia–Alania',
        'Sakha (Yakutia)', 'Tatarstan', 'Tuva', 'Udmurtia',
    ],
    'krai' => [
        'Altai Krai', 'Kamchatka Krai', 'Khabarovsk Krai', 'Krasnodar Krai', 'Krasnoyarsk Krai',
        'Perm Krai', 'Primorsky Krai', 'Stavropol Krai', 'Zabaykalsky Krai',
    ],
    'oblast' => [
        'Amur Oblast', 'Arkhangelsk Oblast', 'Astrakhan Oblast', 'Belgorod Oblast', 'Bryansk Oblast',
        'Chelyabinsk Oblast', 'Irkutsk Oblast', 'Ivanovo Oblast', 'Kaliningrad Oblast',
        'Kaluga Oblast', 'Kemerovo Oblast', 'Kirov Oblast', 'Kostroma Oblast', 'Kurgan Oblast',
        'Kursk Oblast', 'Leningrad Oblast', 'Lipetsk Oblast', 'Magadan Oblast', 'Moscow Oblast',
        'Murmansk Oblast', 'Nizhny Novgorod Oblast', 'Novgorod Oblast', 'Novosibirsk Oblast',
        'Omsk Oblast', 'Orenburg Oblast', 'Oryol Oblast', 'Penza Oblast', 'Pskov Oblast',
        'Rostov Oblast', 'Ryazan Oblast', 'Sakhalin Oblast', 'Samara Oblast', 'Saratov Oblast',
        'Smolensk Oblast', 'Sverdlovsk Oblast', 'Tambov Oblast', 'Tomsk Oblast', 'Tula Oblast',
        'Tver Oblast', 'Tyumen Oblast', 'Ulyanovsk Oblast', 'Vladimir Oblast', 'Volgograd Oblast',
        'Vologda Oblast', 'Voronezh Oblast', 'Yaroslavl Oblast',
    ],
    'federal_city' => ['Moscow', 'Saint Petersburg'],
    'autonomous_oblast' => ['Jewish Autonomous Oblast'],
    'autonomous_okrug' => [
        'Chukotka Autonomous Okrug', 'Khanty-Mansi Autonomous Okrug', 'Nenets Autonomous Okrug',
        'Yamalo-Nenets Autonomous Okrug',
    ],
];

$russiaStates = [];
foreach ($russia as $type => $names) {
    foreach ($names as $name) {
        $russiaStates[] = ['name' => $name, 'type' => $type];
    }
}

return [
    'IN' => [
        'states' => array_merge(
            array_map(fn ($n) => ['name' => $n, 'type' => 'state'], $indiaStates),
            array_map(fn ($n) => ['name' => $n, 'type' => 'union_territory'], $indiaUts),
        ),
        'districts' => [
            'Kerala' => [
                'Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam', 'Idukki',
                'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram', 'Kozhikode', 'Wayanad', 'Kannur',
                'Kasaragod',
            ],
        ],
    ],
    'RU' => [
        'states' => $russiaStates,
        'districts' => [],
    ],
];
