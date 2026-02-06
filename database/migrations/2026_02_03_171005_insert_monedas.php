<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Inserta las monedas según ISO 4217 para las nacionalidades proporcionadas
        // IDMoneda (string, ISO 4217), Moneda (nombre), Fecha (date), timestamps automáticos

        $monedas = [
            ['IDMoneda' => 'AED', 'Moneda' => 'Dirham de Emiratos Árabes Unidos'],
            ['IDMoneda' => 'AFN', 'Moneda' => 'Afgani afgano'],
            ['IDMoneda' => 'ALL', 'Moneda' => 'Lek albanés'],
            ['IDMoneda' => 'AMD', 'Moneda' => 'Dram armenio'],
            ['IDMoneda' => 'ANG', 'Moneda' => 'Florín de las Antillas Neerlandesas'],
            ['IDMoneda' => 'AOA', 'Moneda' => 'Kwanza angoleño'],
            ['IDMoneda' => 'ARS', 'Moneda' => 'Peso argentino'],
            ['IDMoneda' => 'AUD', 'Moneda' => 'Dólar australiano'],
            ['IDMoneda' => 'AWG', 'Moneda' => 'Florín arubeño'],
            ['IDMoneda' => 'AZN', 'Moneda' => 'Manat azerbaiyano'],
            ['IDMoneda' => 'BAM', 'Moneda' => 'Marco convertible de Bosnia y Herzegovina'],
            ['IDMoneda' => 'BBD', 'Moneda' => 'Dólar de Barbados'],
            ['IDMoneda' => 'BDT', 'Moneda' => 'Taka de Bangladés'],
            ['IDMoneda' => 'BGN', 'Moneda' => 'Lev búlgaro'],
            ['IDMoneda' => 'BHD', 'Moneda' => 'Dinar bareiní'],
            ['IDMoneda' => 'BIF', 'Moneda' => 'Franco burundés'],
            ['IDMoneda' => 'BMD', 'Moneda' => 'Dólar de Bermudas'],
            ['IDMoneda' => 'BND', 'Moneda' => 'Dólar de Brunéi'],
            ['IDMoneda' => 'BOB', 'Moneda' => 'Boliviano'],
            ['IDMoneda' => 'BRL', 'Moneda' => 'Real brasileño'],
            ['IDMoneda' => 'BSD', 'Moneda' => 'Dólar bahameño'],
            ['IDMoneda' => 'BTN', 'Moneda' => 'Ngultrum butanés'],
            ['IDMoneda' => 'BWP', 'Moneda' => 'Pula de Botsuana'],
            ['IDMoneda' => 'BYN', 'Moneda' => 'Rublo bielorruso'],
            ['IDMoneda' => 'BZD', 'Moneda' => 'Dólar beliceño'],
            ['IDMoneda' => 'CAD', 'Moneda' => 'Dólar canadiense'],
            ['IDMoneda' => 'CDF', 'Moneda' => 'Franco congoleño'],
            ['IDMoneda' => 'CHF', 'Moneda' => 'Franco suizo'],
            ['IDMoneda' => 'CLP', 'Moneda' => 'Peso chileno'],
            ['IDMoneda' => 'CNY', 'Moneda' => 'Yuan renminbi chino'],
            ['IDMoneda' => 'COP', 'Moneda' => 'Peso colombiano'],
            ['IDMoneda' => 'CRC', 'Moneda' => 'Colón costarricense'],
            ['IDMoneda' => 'CUP', 'Moneda' => 'Peso cubano'],
            ['IDMoneda' => 'CVE', 'Moneda' => 'Escudo caboverdiano'],
            ['IDMoneda' => 'CZK', 'Moneda' => 'Corona checa'],
            ['IDMoneda' => 'DJF', 'Moneda' => 'Franco yibutiano'],
            ['IDMoneda' => 'DKK', 'Moneda' => 'Corona danesa'],
            ['IDMoneda' => 'DOP', 'Moneda' => 'Peso dominicano'],
            ['IDMoneda' => 'DZD', 'Moneda' => 'Dinar argelino'],
            ['IDMoneda' => 'EGP', 'Moneda' => 'Libra egipcia'],
            ['IDMoneda' => 'ERN', 'Moneda' => 'Nakfa eritrea'],
            ['IDMoneda' => 'ETB', 'Moneda' => 'Birr etíope'],
            ['IDMoneda' => 'EUR', 'Moneda' => 'Euro'],
            ['IDMoneda' => 'FJD', 'Moneda' => 'Dólar fiyiano'],
            ['IDMoneda' => 'FKP', 'Moneda' => 'Libra de las Islas Malvinas'],
            ['IDMoneda' => 'GBP', 'Moneda' => 'Libra esterlina'],
            ['IDMoneda' => 'GEL', 'Moneda' => 'Lari georgiano'],
            ['IDMoneda' => 'GHS', 'Moneda' => 'Cedi ghanés'],
            ['IDMoneda' => 'GIP', 'Moneda' => 'Libra de Gibraltar'],
            ['IDMoneda' => 'GMD', 'Moneda' => 'Dalasi gambiano'],
            ['IDMoneda' => 'GNF', 'Moneda' => 'Franco guineano'],
            ['IDMoneda' => 'GTQ', 'Moneda' => 'Quetzal guatemalteco'],
            ['IDMoneda' => 'GYD', 'Moneda' => 'Dólar guyanés'],
            ['IDMoneda' => 'HKD', 'Moneda' => 'Dólar de Hong Kong'],
            ['IDMoneda' => 'HNL', 'Moneda' => 'Lempira hondureña'],
            ['IDMoneda' => 'HRK', 'Moneda' => 'Kuna croata'],     // Nota: Croacia usa EUR desde 2023, pero HRK aún aparece en algunas listas históricas; en 2026 ya es solo EUR
            ['IDMoneda' => 'HTG', 'Moneda' => 'Gourde haitiano'],
            ['IDMoneda' => 'HUF', 'Moneda' => 'Forinto húngaro'],
            ['IDMoneda' => 'IDR', 'Moneda' => 'Rupia indonesia'],
            ['IDMoneda' => 'ILS', 'Moneda' => 'Séquel israelí'],
            ['IDMoneda' => 'INR', 'Moneda' => 'Rupia india'],
            ['IDMoneda' => 'IQD', 'Moneda' => 'Dinar iraquí'],
            ['IDMoneda' => 'IRR', 'Moneda' => 'Rial iraní'],
            ['IDMoneda' => 'ISK', 'Moneda' => 'Corona islandesa'],
            ['IDMoneda' => 'JMD', 'Moneda' => 'Dólar jamaiquino'],
            ['IDMoneda' => 'JOD', 'Moneda' => 'Dinar jordano'],
            ['IDMoneda' => 'JPY', 'Moneda' => 'Yen japonés'],
            ['IDMoneda' => 'KES', 'Moneda' => 'Chelín keniano'],
            ['IDMoneda' => 'KGS', 'Moneda' => 'Som kirguís'],
            ['IDMoneda' => 'KHR', 'Moneda' => 'Riel camboyano'],
            ['IDMoneda' => 'KMF', 'Moneda' => 'Franco comorense'],
            ['IDMoneda' => 'KPW', 'Moneda' => 'Won norcoreano'],
            ['IDMoneda' => 'KRW', 'Moneda' => 'Won surcoreano'],
            ['IDMoneda' => 'KWD', 'Moneda' => 'Dinar kuwaití'],
            ['IDMoneda' => 'KYD', 'Moneda' => 'Dólar de las Islas Caimán'],
            ['IDMoneda' => 'KZT', 'Moneda' => 'Tenge kazajo'],
            ['IDMoneda' => 'LAK', 'Moneda' => 'Kip laosiano'],
            ['IDMoneda' => 'LBP', 'Moneda' => 'Libra libanesa'],
            ['IDMoneda' => 'LKR', 'Moneda' => 'Rupia de Sri Lanka'],
            ['IDMoneda' => 'LRD', 'Moneda' => 'Dólar liberiano'],
            ['IDMoneda' => 'LSL', 'Moneda' => 'Loti lesotense'],
            ['IDMoneda' => 'LYD', 'Moneda' => 'Dinar libio'],
            ['IDMoneda' => 'MAD', 'Moneda' => 'Dírham marroquí'],
            ['IDMoneda' => 'MDL', 'Moneda' => 'Leu moldavo'],
            ['IDMoneda' => 'MGA', 'Moneda' => 'Ariary malgache'],
            ['IDMoneda' => 'MKD', 'Moneda' => 'Denar macedonio'],
            ['IDMoneda' => 'MMK', 'Moneda' => 'Kyat de Myanmar'],
            ['IDMoneda' => 'MNT', 'Moneda' => 'Tugrik mongol'],
            ['IDMoneda' => 'MOP', 'Moneda' => 'Pataca de Macao'],
            ['IDMoneda' => 'MRU', 'Moneda' => 'Ouguiya mauritana'],
            ['IDMoneda' => 'MUR', 'Moneda' => 'Rupia mauricia'],
            ['IDMoneda' => 'MVR', 'Moneda' => 'Rupia de Maldivas'],
            ['IDMoneda' => 'MWK', 'Moneda' => 'Kwacha malauí'],
            ['IDMoneda' => 'MXN', 'Moneda' => 'Peso mexicano'],
            ['IDMoneda' => 'MYR', 'Moneda' => 'Ringgit malayo'],
            ['IDMoneda' => 'MZN', 'Moneda' => 'Metical mozambiqueño'],
            ['IDMoneda' => 'NAD', 'Moneda' => 'Dólar namibio'],
            ['IDMoneda' => 'NGN', 'Moneda' => 'Naira nigeriana'],
            ['IDMoneda' => 'NIO', 'Moneda' => 'Córdoba nicaragüense'],
            ['IDMoneda' => 'NOK', 'Moneda' => 'Corona noruega'],
            ['IDMoneda' => 'NPR', 'Moneda' => 'Rupia nepalí'],
            ['IDMoneda' => 'NZD', 'Moneda' => 'Dólar neozelandés'],
            ['IDMoneda' => 'OMR', 'Moneda' => 'Rial omaní'],
            ['IDMoneda' => 'PAB', 'Moneda' => 'Balboa panameña'],
            ['IDMoneda' => 'PEN', 'Moneda' => 'Sol peruano'],
            ['IDMoneda' => 'PGK', 'Moneda' => 'Kina de Papúa Nueva Guinea'],
            ['IDMoneda' => 'PHP', 'Moneda' => 'Peso filipino'],
            ['IDMoneda' => 'PKR', 'Moneda' => 'Rupia pakistaní'],
            ['IDMoneda' => 'PLN', 'Moneda' => 'Zloty polaco'],
            ['IDMoneda' => 'PYG', 'Moneda' => 'Guaraní paraguayo'],
            ['IDMoneda' => 'QAR', 'Moneda' => 'Rial catarí'],
            ['IDMoneda' => 'RON', 'Moneda' => 'Leu rumano'],
            ['IDMoneda' => 'RSD', 'Moneda' => 'Dinar serbio'],
            ['IDMoneda' => 'RUB', 'Moneda' => 'Rublo ruso'],
            ['IDMoneda' => 'RWF', 'Moneda' => 'Franco ruandés'],
            ['IDMoneda' => 'SAR', 'Moneda' => 'Rial saudí'],
            ['IDMoneda' => 'SBD', 'Moneda' => 'Dólar de las Islas Salomón'],
            ['IDMoneda' => 'SCR', 'Moneda' => 'Rupia seychelense'],
            ['IDMoneda' => 'SDG', 'Moneda' => 'Libra sudanesa'],
            ['IDMoneda' => 'SEK', 'Moneda' => 'Corona sueca'],
            ['IDMoneda' => 'SGD', 'Moneda' => 'Dólar de Singapur'],
            ['IDMoneda' => 'SHP', 'Moneda' => 'Libra de Santa Elena'],
            ['IDMoneda' => 'SLE', 'Moneda' => 'León de Sierra Leona'],
            ['IDMoneda' => 'SLL', 'Moneda' => 'León de Sierra Leona (viejo)'], // SLE es el nuevo, SLL aún en transición
            ['IDMoneda' => 'SOS', 'Moneda' => 'Chelín somalí'],
            ['IDMoneda' => 'SRD', 'Moneda' => 'Dólar surinamés'],
            ['IDMoneda' => 'SSP', 'Moneda' => 'Libra sursudanesa'],
            ['IDMoneda' => 'STN', 'Moneda' => 'Dobra de Santo Tomé y Príncipe'],
            ['IDMoneda' => 'SVC', 'Moneda' => 'Colón salvadoreño'], // Aunque El Salvador usa USD oficialmente
            ['IDMoneda' => 'SYP', 'Moneda' => 'Libra siria'],
            ['IDMoneda' => 'SZL', 'Moneda' => 'Lilangeni suazi'],
            ['IDMoneda' => 'THB', 'Moneda' => 'Baht tailandés'],
            ['IDMoneda' => 'TJS', 'Moneda' => 'Somoni tayiko'],
            ['IDMoneda' => 'TMT', 'Moneda' => 'Manat turcomano'],
            ['IDMoneda' => 'TND', 'Moneda' => 'Dinar tunecino'],
            ['IDMoneda' => 'TOP', 'Moneda' => 'Paʻanga tongano'],
            ['IDMoneda' => 'TRY', 'Moneda' => 'Lira turca'],
            ['IDMoneda' => 'TTD', 'Moneda' => 'Dólar de Trinidad y Tobago'],
            ['IDMoneda' => 'TWD', 'Moneda' => 'Nuevo dólar taiwanés'],
            ['IDMoneda' => 'TZS', 'Moneda' => 'Chelín tanzano'],
            ['IDMoneda' => 'UAH', 'Moneda' => 'Hryvnia ucraniana'],
            ['IDMoneda' => 'UGX', 'Moneda' => 'Chelín ugandés'],
            ['IDMoneda' => 'USD', 'Moneda' => 'Dólar estadounidense'],
            ['IDMoneda' => 'UYU', 'Moneda' => 'Peso uruguayo'],
            ['IDMoneda' => 'UZS', 'Moneda' => 'Som uzbeko'],
            ['IDMoneda' => 'VES', 'Moneda' => 'Bolívar soberano venezolano'],
            ['IDMoneda' => 'VND', 'Moneda' => 'Dong vietnamita'],
            ['IDMoneda' => 'VUV', 'Moneda' => 'Vatu de Vanuatu'],
            ['IDMoneda' => 'WST', 'Moneda' => 'Tala samoano'],
            ['IDMoneda' => 'XAF', 'Moneda' => 'Franco CFA de África Central'],
            ['IDMoneda' => 'XCD', 'Moneda' => 'Dólar del Caribe Oriental'],
            ['IDMoneda' => 'XOF', 'Moneda' => 'Franco CFA de África Occidental'],
            ['IDMoneda' => 'XPF', 'Moneda' => 'Franco CFP'],
            ['IDMoneda' => 'YER', 'Moneda' => 'Rial yemení'],
            ['IDMoneda' => 'ZAR', 'Moneda' => 'Rand sudafricano'],
            ['IDMoneda' => 'ZMW', 'Moneda' => 'Kwacha zambiano'],
            ['IDMoneda' => 'ZWG', 'Moneda' => 'Zimbabwe Gold'],  // Moneda actual de Zimbabue en 2026
        ];

        // Añadir entradas únicas, algunas nacionalidades comparten moneda
        DB::table('catMonedas')->insertOrIgnore(
            collect($monedas)
                ->unique('IDMoneda')
                ->map(function ($m) {
                    return [
                        'IDMoneda' => $m['IDMoneda'],
                        'Moneda' => $m['Moneda'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina SOLO las monedas insertadas por esta migración
        $ids = [
            'MXN',
            'USD',
            'CAD',
            'ARS',
            'BRL',
            'CLP',
            'COP',
            'EUR',
            'GBP',
            'JPY',
            'CNY',
            'INR',
        ];
        DB::table('catMonedas')->whereIn('IDMoneda', $ids)->delete();
    }
};
