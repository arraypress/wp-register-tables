<?php
/**
 * WordPress Currencies Library for Stripe
 *
 * A comprehensive currency formatting and conversion library for Stripe payments in WordPress.
 *
 * @package     ArrayPress\Currencies
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.1.0
 * @author      David Sherlock
 */

namespace ArrayPress\Currencies;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Currency class for Stripe payment processing
 *
 * Handles currency formatting, conversion, and validation for all
 * Stripe-supported currencies with proper decimal handling.
 *
 * @since 1.0.0
 */
class Currency {

	/**
	 * Stripe supported currencies with configuration.
	 *
	 * Complete list as of 2025 - 136 currencies.
	 *
	 * The `decimals` value reflects what Stripe's API expects, not necessarily the
	 * currency's logical decimal places. Some currencies (ISK, UGX) are logically
	 * zero-decimal but Stripe requires two-decimal representation for backward
	 * compatibility. HUF and TWD accept two-decimal charges but are zero-decimal
	 * for payouts only.
	 *
	 * The `country` value uses ISO 3166-1 alpha-2 codes for the currency's primary
	 * country of origin. Regional/supranational currencies use descriptive names
	 * with an empty country code.
	 *
	 * @link  https://stripe.com/docs/currencies
	 * @since 1.0.0
	 * @var array
	 */
	private const CURRENCIES = [

		// Major Currencies
		'USD' => [ 'name'         => 'US Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_US',
		           'country'      => 'US',
		           'country_name' => 'United States'
		],
		'EUR' => [ 'name'         => 'Euro',
		           'symbol'       => '€',
		           'decimals'     => 2,
		           'locale'       => 'de_DE',
		           'country'      => 'EU',
		           'country_name' => 'Eurozone'
		],
		'GBP' => [ 'name'         => 'British Pound',
		           'symbol'       => '£',
		           'decimals'     => 2,
		           'locale'       => 'en_GB',
		           'country'      => 'GB',
		           'country_name' => 'United Kingdom'
		],
		'JPY' => [ 'name'         => 'Japanese Yen',
		           'symbol'       => '¥',
		           'decimals'     => 0,
		           'locale'       => 'ja_JP',
		           'country'      => 'JP',
		           'country_name' => 'Japan'
		],
		'CNY' => [ 'name'         => 'Chinese Yuan',
		           'symbol'       => '¥',
		           'decimals'     => 2,
		           'locale'       => 'zh_CN',
		           'country'      => 'CN',
		           'country_name' => 'China'
		],

		// Americas
		'CAD' => [ 'name'         => 'Canadian Dollar',
		           'symbol'       => 'C$',
		           'decimals'     => 2,
		           'locale'       => 'en_CA',
		           'country'      => 'CA',
		           'country_name' => 'Canada'
		],
		'MXN' => [ 'name'         => 'Mexican Peso',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'es_MX',
		           'country'      => 'MX',
		           'country_name' => 'Mexico'
		],
		'BRL' => [ 'name'         => 'Brazilian Real',
		           'symbol'       => 'R$',
		           'decimals'     => 2,
		           'locale'       => 'pt_BR',
		           'country'      => 'BR',
		           'country_name' => 'Brazil'
		],
		'ARS' => [ 'name'         => 'Argentine Peso',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'es_AR',
		           'country'      => 'AR',
		           'country_name' => 'Argentina'
		],
		'COP' => [ 'name'         => 'Colombian Peso',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'es_CO',
		           'country'      => 'CO',
		           'country_name' => 'Colombia'
		],
		'PEN' => [ 'name'         => 'Peruvian Sol',
		           'symbol'       => 'S/',
		           'decimals'     => 2,
		           'locale'       => 'es_PE',
		           'country'      => 'PE',
		           'country_name' => 'Peru'
		],
		'CLP' => [ 'name'         => 'Chilean Peso',
		           'symbol'       => '$',
		           'decimals'     => 0,
		           'locale'       => 'es_CL',
		           'country'      => 'CL',
		           'country_name' => 'Chile'
		],
		'UYU' => [ 'name'         => 'Uruguayan Peso',
		           'symbol'       => '$U',
		           'decimals'     => 2,
		           'locale'       => 'es_UY',
		           'country'      => 'UY',
		           'country_name' => 'Uruguay'
		],
		'PYG' => [ 'name'         => 'Paraguayan Guarani',
		           'symbol'       => '₲',
		           'decimals'     => 0,
		           'locale'       => 'es_PY',
		           'country'      => 'PY',
		           'country_name' => 'Paraguay'
		],
		'BOB' => [ 'name'         => 'Bolivian Boliviano',
		           'symbol'       => 'Bs',
		           'decimals'     => 2,
		           'locale'       => 'es_BO',
		           'country'      => 'BO',
		           'country_name' => 'Bolivia'
		],
		'CRC' => [ 'name'         => 'Costa Rican Colón',
		           'symbol'       => '₡',
		           'decimals'     => 2,
		           'locale'       => 'es_CR',
		           'country'      => 'CR',
		           'country_name' => 'Costa Rica'
		],
		'DOP' => [ 'name'         => 'Dominican Peso',
		           'symbol'       => 'RD$',
		           'decimals'     => 2,
		           'locale'       => 'es_DO',
		           'country'      => 'DO',
		           'country_name' => 'Dominican Republic'
		],
		'GTQ' => [ 'name'         => 'Guatemalan Quetzal',
		           'symbol'       => 'Q',
		           'decimals'     => 2,
		           'locale'       => 'es_GT',
		           'country'      => 'GT',
		           'country_name' => 'Guatemala'
		],
		'HNL' => [ 'name'         => 'Honduran Lempira',
		           'symbol'       => 'L',
		           'decimals'     => 2,
		           'locale'       => 'es_HN',
		           'country'      => 'HN',
		           'country_name' => 'Honduras'
		],
		'NIO' => [ 'name'         => 'Nicaraguan Córdoba',
		           'symbol'       => 'C$',
		           'decimals'     => 2,
		           'locale'       => 'es_NI',
		           'country'      => 'NI',
		           'country_name' => 'Nicaragua'
		],
		'PAB' => [ 'name'         => 'Panamanian Balboa',
		           'symbol'       => 'B/',
		           'decimals'     => 2,
		           'locale'       => 'es_PA',
		           'country'      => 'PA',
		           'country_name' => 'Panama'
		],

		// Europe (Non-Euro)
		'CHF' => [ 'name'         => 'Swiss Franc',
		           'symbol'       => 'CHF',
		           'decimals'     => 2,
		           'locale'       => 'de_CH',
		           'country'      => 'CH',
		           'country_name' => 'Switzerland'
		],
		'SEK' => [ 'name'         => 'Swedish Krona',
		           'symbol'       => 'kr',
		           'decimals'     => 2,
		           'locale'       => 'sv_SE',
		           'country'      => 'SE',
		           'country_name' => 'Sweden'
		],
		'DKK' => [ 'name'         => 'Danish Krone',
		           'symbol'       => 'kr',
		           'decimals'     => 2,
		           'locale'       => 'da_DK',
		           'country'      => 'DK',
		           'country_name' => 'Denmark'
		],
		'NOK' => [ 'name'         => 'Norwegian Krone',
		           'symbol'       => 'kr',
		           'decimals'     => 2,
		           'locale'       => 'nb_NO',
		           'country'      => 'NO',
		           'country_name' => 'Norway'
		],
		'ISK' => [ 'name'         => 'Icelandic Króna',
		           'symbol'       => 'kr',
		           'decimals'     => 2,
		           'locale'       => 'is_IS',
		           'country'      => 'IS',
		           'country_name' => 'Iceland'
		],
		'PLN' => [ 'name'         => 'Polish Złoty',
		           'symbol'       => 'zł',
		           'decimals'     => 2,
		           'locale'       => 'pl_PL',
		           'country'      => 'PL',
		           'country_name' => 'Poland'
		],
		'CZK' => [ 'name'         => 'Czech Koruna',
		           'symbol'       => 'Kč',
		           'decimals'     => 2,
		           'locale'       => 'cs_CZ',
		           'country'      => 'CZ',
		           'country_name' => 'Czechia'
		],
		'HUF' => [ 'name'         => 'Hungarian Forint',
		           'symbol'       => 'Ft',
		           'decimals'     => 2,
		           'locale'       => 'hu_HU',
		           'country'      => 'HU',
		           'country_name' => 'Hungary'
		],
		'RON' => [ 'name'         => 'Romanian Leu',
		           'symbol'       => 'lei',
		           'decimals'     => 2,
		           'locale'       => 'ro_RO',
		           'country'      => 'RO',
		           'country_name' => 'Romania'
		],
		'BGN' => [ 'name'         => 'Bulgarian Lev',
		           'symbol'       => 'лв',
		           'decimals'     => 2,
		           'locale'       => 'bg_BG',
		           'country'      => 'BG',
		           'country_name' => 'Bulgaria'
		],
		'RSD' => [ 'name'         => 'Serbian Dinar',
		           'symbol'       => 'din',
		           'decimals'     => 2,
		           'locale'       => 'sr_RS',
		           'country'      => 'RS',
		           'country_name' => 'Serbia'
		],
		'MKD' => [ 'name'         => 'Macedonian Denar',
		           'symbol'       => 'ден',
		           'decimals'     => 2,
		           'locale'       => 'mk_MK',
		           'country'      => 'MK',
		           'country_name' => 'North Macedonia'
		],
		'MDL' => [ 'name'         => 'Moldovan Leu',
		           'symbol'       => 'L',
		           'decimals'     => 2,
		           'locale'       => 'ro_MD',
		           'country'      => 'MD',
		           'country_name' => 'Moldova'
		],
		'UAH' => [ 'name'         => 'Ukrainian Hryvnia',
		           'symbol'       => '₴',
		           'decimals'     => 2,
		           'locale'       => 'uk_UA',
		           'country'      => 'UA',
		           'country_name' => 'Ukraine'
		],
		'GEL' => [ 'name'         => 'Georgian Lari',
		           'symbol'       => '₾',
		           'decimals'     => 2,
		           'locale'       => 'ka_GE',
		           'country'      => 'GE',
		           'country_name' => 'Georgia'
		],
		'ALL' => [ 'name'         => 'Albanian Lek',
		           'symbol'       => 'L',
		           'decimals'     => 2,
		           'locale'       => 'sq_AL',
		           'country'      => 'AL',
		           'country_name' => 'Albania'
		],
		'BAM' => [ 'name'         => 'Bosnia-Herzegovina Convertible Mark',
		           'symbol'       => 'KM',
		           'decimals'     => 2,
		           'locale'       => 'bs_BA',
		           'country'      => 'BA',
		           'country_name' => 'Bosnia and Herzegovina'
		],

		// Asia-Pacific
		'HKD' => [ 'name'         => 'Hong Kong Dollar',
		           'symbol'       => 'HK$',
		           'decimals'     => 2,
		           'locale'       => 'zh_HK',
		           'country'      => 'HK',
		           'country_name' => 'Hong Kong'
		],
		'TWD' => [ 'name'         => 'New Taiwan Dollar',
		           'symbol'       => 'NT$',
		           'decimals'     => 2,
		           'locale'       => 'zh_TW',
		           'country'      => 'TW',
		           'country_name' => 'Taiwan'
		],
		'KRW' => [ 'name'         => 'South Korean Won',
		           'symbol'       => '₩',
		           'decimals'     => 0,
		           'locale'       => 'ko_KR',
		           'country'      => 'KR',
		           'country_name' => 'South Korea'
		],
		'SGD' => [ 'name'         => 'Singapore Dollar',
		           'symbol'       => 'S$',
		           'decimals'     => 2,
		           'locale'       => 'en_SG',
		           'country'      => 'SG',
		           'country_name' => 'Singapore'
		],
		'THB' => [ 'name'         => 'Thai Baht',
		           'symbol'       => '฿',
		           'decimals'     => 2,
		           'locale'       => 'th_TH',
		           'country'      => 'TH',
		           'country_name' => 'Thailand'
		],
		'MYR' => [ 'name'         => 'Malaysian Ringgit',
		           'symbol'       => 'RM',
		           'decimals'     => 2,
		           'locale'       => 'ms_MY',
		           'country'      => 'MY',
		           'country_name' => 'Malaysia'
		],
		'PHP' => [ 'name'         => 'Philippine Peso',
		           'symbol'       => '₱',
		           'decimals'     => 2,
		           'locale'       => 'en_PH',
		           'country'      => 'PH',
		           'country_name' => 'Philippines'
		],
		'IDR' => [ 'name'         => 'Indonesian Rupiah',
		           'symbol'       => 'Rp',
		           'decimals'     => 2,
		           'locale'       => 'id_ID',
		           'country'      => 'ID',
		           'country_name' => 'Indonesia'
		],
		'VND' => [ 'name'         => 'Vietnamese Đồng',
		           'symbol'       => '₫',
		           'decimals'     => 0,
		           'locale'       => 'vi_VN',
		           'country'      => 'VN',
		           'country_name' => 'Vietnam'
		],
		'INR' => [ 'name'         => 'Indian Rupee',
		           'symbol'       => '₹',
		           'decimals'     => 2,
		           'locale'       => 'en_IN',
		           'country'      => 'IN',
		           'country_name' => 'India'
		],
		'PKR' => [ 'name'         => 'Pakistani Rupee',
		           'symbol'       => '₨',
		           'decimals'     => 2,
		           'locale'       => 'ur_PK',
		           'country'      => 'PK',
		           'country_name' => 'Pakistan'
		],
		'BDT' => [ 'name'         => 'Bangladeshi Taka',
		           'symbol'       => '৳',
		           'decimals'     => 2,
		           'locale'       => 'bn_BD',
		           'country'      => 'BD',
		           'country_name' => 'Bangladesh'
		],
		'LKR' => [ 'name'         => 'Sri Lankan Rupee',
		           'symbol'       => 'Rs',
		           'decimals'     => 2,
		           'locale'       => 'si_LK',
		           'country'      => 'LK',
		           'country_name' => 'Sri Lanka'
		],
		'NPR' => [ 'name'         => 'Nepalese Rupee',
		           'symbol'       => '₨',
		           'decimals'     => 2,
		           'locale'       => 'ne_NP',
		           'country'      => 'NP',
		           'country_name' => 'Nepal'
		],
		'MMK' => [ 'name'         => 'Myanmar Kyat',
		           'symbol'       => 'K',
		           'decimals'     => 2,
		           'locale'       => 'my_MM',
		           'country'      => 'MM',
		           'country_name' => 'Myanmar'
		],
		'KHR' => [ 'name'         => 'Cambodian Riel',
		           'symbol'       => '៛',
		           'decimals'     => 2,
		           'locale'       => 'km_KH',
		           'country'      => 'KH',
		           'country_name' => 'Cambodia'
		],
		'LAK' => [ 'name'         => 'Lao Kip',
		           'symbol'       => '₭',
		           'decimals'     => 2,
		           'locale'       => 'lo_LA',
		           'country'      => 'LA',
		           'country_name' => 'Laos'
		],
		'MNT' => [ 'name'         => 'Mongolian Tögrög',
		           'symbol'       => '₮',
		           'decimals'     => 2,
		           'locale'       => 'mn_MN',
		           'country'      => 'MN',
		           'country_name' => 'Mongolia'
		],
		'BND' => [ 'name'         => 'Brunei Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'ms_BN',
		           'country'      => 'BN',
		           'country_name' => 'Brunei'
		],
		'PGK' => [ 'name'         => 'Papua New Guinean Kina',
		           'symbol'       => 'K',
		           'decimals'     => 2,
		           'locale'       => 'en_PG',
		           'country'      => 'PG',
		           'country_name' => 'Papua New Guinea'
		],
		'FJD' => [ 'name'         => 'Fijian Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_FJ',
		           'country'      => 'FJ',
		           'country_name' => 'Fiji'
		],
		'SBD' => [ 'name'         => 'Solomon Islands Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_SB',
		           'country'      => 'SB',
		           'country_name' => 'Solomon Islands'
		],
		'TOP' => [ 'name'         => 'Tongan Paʻanga',
		           'symbol'       => 'T$',
		           'decimals'     => 2,
		           'locale'       => 'to_TO',
		           'country'      => 'TO',
		           'country_name' => 'Tonga'
		],
		'VUV' => [ 'name'         => 'Vanuatu Vatu',
		           'symbol'       => 'VT',
		           'decimals'     => 0,
		           'locale'       => 'en_VU',
		           'country'      => 'VU',
		           'country_name' => 'Vanuatu'
		],
		'WST' => [ 'name'         => 'Samoan Tālā',
		           'symbol'       => 'WS$',
		           'decimals'     => 2,
		           'locale'       => 'en_WS',
		           'country'      => 'WS',
		           'country_name' => 'Samoa'
		],
		'MVR' => [ 'name'         => 'Maldivian Rufiyaa',
		           'symbol'       => 'Rf',
		           'decimals'     => 2,
		           'locale'       => 'dv_MV',
		           'country'      => 'MV',
		           'country_name' => 'Maldives'
		],

		// Oceania
		'AUD' => [ 'name'         => 'Australian Dollar',
		           'symbol'       => 'A$',
		           'decimals'     => 2,
		           'locale'       => 'en_AU',
		           'country'      => 'AU',
		           'country_name' => 'Australia'
		],
		'NZD' => [ 'name'         => 'New Zealand Dollar',
		           'symbol'       => 'NZ$',
		           'decimals'     => 2,
		           'locale'       => 'en_NZ',
		           'country'      => 'NZ',
		           'country_name' => 'New Zealand'
		],

		// Middle East
		'AED' => [ 'name'         => 'United Arab Emirates Dirham',
		           'symbol'       => 'د.إ',
		           'decimals'     => 2,
		           'locale'       => 'ar_AE',
		           'country'      => 'AE',
		           'country_name' => 'United Arab Emirates'
		],
		'SAR' => [ 'name'         => 'Saudi Riyal',
		           'symbol'       => 'SR',
		           'decimals'     => 2,
		           'locale'       => 'ar_SA',
		           'country'      => 'SA',
		           'country_name' => 'Saudi Arabia'
		],
		'QAR' => [ 'name'         => 'Qatari Riyal',
		           'symbol'       => 'QR',
		           'decimals'     => 2,
		           'locale'       => 'ar_QA',
		           'country'      => 'QA',
		           'country_name' => 'Qatar'
		],
		'OMR' => [ 'name'         => 'Omani Rial',
		           'symbol'       => 'ر.ع.',
		           'decimals'     => 3,
		           'locale'       => 'ar_OM',
		           'country'      => 'OM',
		           'country_name' => 'Oman'
		],
		'KWD' => [ 'name'         => 'Kuwaiti Dinar',
		           'symbol'       => 'KD',
		           'decimals'     => 3,
		           'locale'       => 'ar_KW',
		           'country'      => 'KW',
		           'country_name' => 'Kuwait'
		],
		'BHD' => [ 'name'         => 'Bahraini Dinar',
		           'symbol'       => 'BD',
		           'decimals'     => 3,
		           'locale'       => 'ar_BH',
		           'country'      => 'BH',
		           'country_name' => 'Bahrain'
		],
		'JOD' => [ 'name'         => 'Jordanian Dinar',
		           'symbol'       => 'JD',
		           'decimals'     => 3,
		           'locale'       => 'ar_JO',
		           'country'      => 'JO',
		           'country_name' => 'Jordan'
		],
		'ILS' => [ 'name'         => 'Israeli New Shekel',
		           'symbol'       => '₪',
		           'decimals'     => 2,
		           'locale'       => 'he_IL',
		           'country'      => 'IL',
		           'country_name' => 'Israel'
		],
		'TRY' => [ 'name'         => 'Turkish Lira',
		           'symbol'       => '₺',
		           'decimals'     => 2,
		           'locale'       => 'tr_TR',
		           'country'      => 'TR',
		           'country_name' => 'Turkey'
		],
		'LBP' => [ 'name'         => 'Lebanese Pound',
		           'symbol'       => 'ل.ل',
		           'decimals'     => 2,
		           'locale'       => 'ar_LB',
		           'country'      => 'LB',
		           'country_name' => 'Lebanon'
		],

		// Africa
		'ZAR' => [ 'name'         => 'South African Rand',
		           'symbol'       => 'R',
		           'decimals'     => 2,
		           'locale'       => 'en_ZA',
		           'country'      => 'ZA',
		           'country_name' => 'South Africa'
		],
		'EGP' => [ 'name'         => 'Egyptian Pound',
		           'symbol'       => 'E£',
		           'decimals'     => 2,
		           'locale'       => 'ar_EG',
		           'country'      => 'EG',
		           'country_name' => 'Egypt'
		],
		'NGN' => [ 'name'         => 'Nigerian Naira',
		           'symbol'       => '₦',
		           'decimals'     => 2,
		           'locale'       => 'en_NG',
		           'country'      => 'NG',
		           'country_name' => 'Nigeria'
		],
		'KES' => [ 'name'         => 'Kenyan Shilling',
		           'symbol'       => 'KSh',
		           'decimals'     => 2,
		           'locale'       => 'en_KE',
		           'country'      => 'KE',
		           'country_name' => 'Kenya'
		],
		'GHS' => [ 'name'         => 'Ghanaian Cedi',
		           'symbol'       => '₵',
		           'decimals'     => 2,
		           'locale'       => 'en_GH',
		           'country'      => 'GH',
		           'country_name' => 'Ghana'
		],
		'MAD' => [ 'name'         => 'Moroccan Dirham',
		           'symbol'       => 'MAD',
		           'decimals'     => 2,
		           'locale'       => 'ar_MA',
		           'country'      => 'MA',
		           'country_name' => 'Morocco'
		],
		'TND' => [ 'name'         => 'Tunisian Dinar',
		           'symbol'       => 'DT',
		           'decimals'     => 3,
		           'locale'       => 'ar_TN',
		           'country'      => 'TN',
		           'country_name' => 'Tunisia'
		],
		'DZD' => [ 'name'         => 'Algerian Dinar',
		           'symbol'       => 'DA',
		           'decimals'     => 2,
		           'locale'       => 'ar_DZ',
		           'country'      => 'DZ',
		           'country_name' => 'Algeria'
		],
		'ETB' => [ 'name'         => 'Ethiopian Birr',
		           'symbol'       => 'Br',
		           'decimals'     => 2,
		           'locale'       => 'am_ET',
		           'country'      => 'ET',
		           'country_name' => 'Ethiopia'
		],
		'UGX' => [ 'name'         => 'Ugandan Shilling',
		           'symbol'       => 'USh',
		           'decimals'     => 2,
		           'locale'       => 'en_UG',
		           'country'      => 'UG',
		           'country_name' => 'Uganda'
		],
		'TZS' => [ 'name'         => 'Tanzanian Shilling',
		           'symbol'       => 'TSh',
		           'decimals'     => 2,
		           'locale'       => 'en_TZ',
		           'country'      => 'TZ',
		           'country_name' => 'Tanzania'
		],
		'RWF' => [ 'name'         => 'Rwandan Franc',
		           'symbol'       => 'FRw',
		           'decimals'     => 0,
		           'locale'       => 'rw_RW',
		           'country'      => 'RW',
		           'country_name' => 'Rwanda'
		],
		'MUR' => [ 'name'         => 'Mauritian Rupee',
		           'symbol'       => '₨',
		           'decimals'     => 2,
		           'locale'       => 'en_MU',
		           'country'      => 'MU',
		           'country_name' => 'Mauritius'
		],
		'SCR' => [ 'name'         => 'Seychellois Rupee',
		           'symbol'       => '₨',
		           'decimals'     => 2,
		           'locale'       => 'en_SC',
		           'country'      => 'SC',
		           'country_name' => 'Seychelles'
		],
		'MZN' => [ 'name'         => 'Mozambican Metical',
		           'symbol'       => 'MT',
		           'decimals'     => 2,
		           'locale'       => 'pt_MZ',
		           'country'      => 'MZ',
		           'country_name' => 'Mozambique'
		],
		'ZMW' => [ 'name'         => 'Zambian Kwacha',
		           'symbol'       => 'ZK',
		           'decimals'     => 2,
		           'locale'       => 'en_ZM',
		           'country'      => 'ZM',
		           'country_name' => 'Zambia'
		],
		'BWP' => [ 'name'         => 'Botswanan Pula',
		           'symbol'       => 'P',
		           'decimals'     => 2,
		           'locale'       => 'en_BW',
		           'country'      => 'BW',
		           'country_name' => 'Botswana'
		],
		'NAD' => [ 'name'         => 'Namibian Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_NA',
		           'country'      => 'NA',
		           'country_name' => 'Namibia'
		],
		'SZL' => [ 'name'         => 'Swazi Lilangeni',
		           'symbol'       => 'L',
		           'decimals'     => 2,
		           'locale'       => 'en_SZ',
		           'country'      => 'SZ',
		           'country_name' => 'Eswatini'
		],
		'LSL' => [ 'name'         => 'Lesotho Loti',
		           'symbol'       => 'L',
		           'decimals'     => 2,
		           'locale'       => 'en_LS',
		           'country'      => 'LS',
		           'country_name' => 'Lesotho'
		],
		'MWK' => [ 'name'         => 'Malawian Kwacha',
		           'symbol'       => 'MK',
		           'decimals'     => 2,
		           'locale'       => 'en_MW',
		           'country'      => 'MW',
		           'country_name' => 'Malawi'
		],
		'AOA' => [ 'name'         => 'Angolan Kwanza',
		           'symbol'       => 'Kz',
		           'decimals'     => 2,
		           'locale'       => 'pt_AO',
		           'country'      => 'AO',
		           'country_name' => 'Angola'
		],
		'BIF' => [ 'name'         => 'Burundian Franc',
		           'symbol'       => 'FBu',
		           'decimals'     => 0,
		           'locale'       => 'rn_BI',
		           'country'      => 'BI',
		           'country_name' => 'Burundi'
		],
		'DJF' => [ 'name'         => 'Djiboutian Franc',
		           'symbol'       => 'Fdj',
		           'decimals'     => 0,
		           'locale'       => 'fr_DJ',
		           'country'      => 'DJ',
		           'country_name' => 'Djibouti'
		],
		'GNF' => [ 'name'         => 'Guinean Franc',
		           'symbol'       => 'FG',
		           'decimals'     => 0,
		           'locale'       => 'fr_GN',
		           'country'      => 'GN',
		           'country_name' => 'Guinea'
		],
		'KMF' => [ 'name'         => 'Comorian Franc',
		           'symbol'       => 'CF',
		           'decimals'     => 0,
		           'locale'       => 'fr_KM',
		           'country'      => 'KM',
		           'country_name' => 'Comoros'
		],
		'CDF' => [ 'name'         => 'Congolese Franc',
		           'symbol'       => 'FC',
		           'decimals'     => 2,
		           'locale'       => 'fr_CD',
		           'country'      => 'CD',
		           'country_name' => 'Democratic Republic of the Congo'
		],
		'MGA' => [ 'name'         => 'Malagasy Ariary',
		           'symbol'       => 'Ar',
		           'decimals'     => 0,
		           'locale'       => 'mg_MG',
		           'country'      => 'MG',
		           'country_name' => 'Madagascar'
		],
		'XAF' => [ 'name'         => 'Central African CFA Franc',
		           'symbol'       => 'FCFA',
		           'decimals'     => 0,
		           'locale'       => 'fr_CM',
		           'country'      => 'CM',
		           'country_name' => 'Central Africa'
		],
		'XOF' => [ 'name'         => 'West African CFA Franc',
		           'symbol'       => 'CFA',
		           'decimals'     => 0,
		           'locale'       => 'fr_SN',
		           'country'      => 'SN',
		           'country_name' => 'West Africa'
		],

		// Caribbean
		'JMD' => [ 'name'         => 'Jamaican Dollar',
		           'symbol'       => 'J$',
		           'decimals'     => 2,
		           'locale'       => 'en_JM',
		           'country'      => 'JM',
		           'country_name' => 'Jamaica'
		],
		'TTD' => [ 'name'         => 'Trinidad & Tobago Dollar',
		           'symbol'       => 'TT$',
		           'decimals'     => 2,
		           'locale'       => 'en_TT',
		           'country'      => 'TT',
		           'country_name' => 'Trinidad and Tobago'
		],
		'BBD' => [ 'name'         => 'Barbadian Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_BB',
		           'country'      => 'BB',
		           'country_name' => 'Barbados'
		],
		'BSD' => [ 'name'         => 'Bahamian Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_BS',
		           'country'      => 'BS',
		           'country_name' => 'Bahamas'
		],
		'BZD' => [ 'name'         => 'Belize Dollar',
		           'symbol'       => 'BZ$',
		           'decimals'     => 2,
		           'locale'       => 'en_BZ',
		           'country'      => 'BZ',
		           'country_name' => 'Belize'
		],
		'BMD' => [ 'name'         => 'Bermudan Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_BM',
		           'country'      => 'BM',
		           'country_name' => 'Bermuda'
		],
		'KYD' => [ 'name'         => 'Cayman Islands Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_KY',
		           'country'      => 'KY',
		           'country_name' => 'Cayman Islands'
		],
		'XCD' => [ 'name'         => 'East Caribbean Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_AG',
		           'country'      => 'AG',
		           'country_name' => 'East Caribbean'
		],
		'AWG' => [ 'name'         => 'Aruban Florin',
		           'symbol'       => 'ƒ',
		           'decimals'     => 2,
		           'locale'       => 'nl_AW',
		           'country'      => 'AW',
		           'country_name' => 'Aruba'
		],
		'ANG' => [ 'name'         => 'Netherlands Antillean Guilder',
		           'symbol'       => 'ƒ',
		           'decimals'     => 2,
		           'locale'       => 'nl_CW',
		           'country'      => 'CW',
		           'country_name' => 'Curaçao'
		],
		'HTG' => [ 'name'         => 'Haitian Gourde',
		           'symbol'       => 'G',
		           'decimals'     => 2,
		           'locale'       => 'fr_HT',
		           'country'      => 'HT',
		           'country_name' => 'Haiti'
		],

		// Former Soviet States
		'RUB' => [ 'name'         => 'Russian Ruble',
		           'symbol'       => '₽',
		           'decimals'     => 2,
		           'locale'       => 'ru_RU',
		           'country'      => 'RU',
		           'country_name' => 'Russia'
		],
		'KZT' => [ 'name'         => 'Kazakhstani Tenge',
		           'symbol'       => '₸',
		           'decimals'     => 2,
		           'locale'       => 'kk_KZ',
		           'country'      => 'KZ',
		           'country_name' => 'Kazakhstan'
		],
		'UZS' => [ 'name'         => 'Uzbekistani Som',
		           'symbol'       => 'лв',
		           'decimals'     => 2,
		           'locale'       => 'uz_UZ',
		           'country'      => 'UZ',
		           'country_name' => 'Uzbekistan'
		],
		'AZN' => [ 'name'         => 'Azerbaijani Manat',
		           'symbol'       => '₼',
		           'decimals'     => 2,
		           'locale'       => 'az_AZ',
		           'country'      => 'AZ',
		           'country_name' => 'Azerbaijan'
		],
		'AMD' => [ 'name'         => 'Armenian Dram',
		           'symbol'       => '֏',
		           'decimals'     => 2,
		           'locale'       => 'hy_AM',
		           'country'      => 'AM',
		           'country_name' => 'Armenia'
		],
		'KGS' => [ 'name'         => 'Kyrgystani Som',
		           'symbol'       => 'лв',
		           'decimals'     => 2,
		           'locale'       => 'ky_KG',
		           'country'      => 'KG',
		           'country_name' => 'Kyrgyzstan'
		],
		'TJS' => [ 'name'         => 'Tajikistani Somoni',
		           'symbol'       => 'SM',
		           'decimals'     => 2,
		           'locale'       => 'tg_TJ',
		           'country'      => 'TJ',
		           'country_name' => 'Tajikistan'
		],
		'TMT' => [ 'name'         => 'Turkmenistani Manat',
		           'symbol'       => 'T',
		           'decimals'     => 2,
		           'locale'       => 'tk_TM',
		           'country'      => 'TM',
		           'country_name' => 'Turkmenistan'
		],

		// Other
		'AFN' => [ 'name'         => 'Afghan Afghani',
		           'symbol'       => '؋',
		           'decimals'     => 2,
		           'locale'       => 'fa_AF',
		           'country'      => 'AF',
		           'country_name' => 'Afghanistan'
		],
		'XPF' => [ 'name'         => 'CFP Franc',
		           'symbol'       => '₣',
		           'decimals'     => 0,
		           'locale'       => 'fr_PF',
		           'country'      => 'PF',
		           'country_name' => 'French Polynesia'
		],
		'CVE' => [ 'name'         => 'Cape Verdean Escudo',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'pt_CV',
		           'country'      => 'CV',
		           'country_name' => 'Cape Verde'
		],
		'GIP' => [ 'name'         => 'Gibraltar Pound',
		           'symbol'       => '£',
		           'decimals'     => 2,
		           'locale'       => 'en_GI',
		           'country'      => 'GI',
		           'country_name' => 'Gibraltar'
		],
		'GMD' => [ 'name'         => 'Gambian Dalasi',
		           'symbol'       => 'D',
		           'decimals'     => 2,
		           'locale'       => 'en_GM',
		           'country'      => 'GM',
		           'country_name' => 'Gambia'
		],
		'GYD' => [ 'name'         => 'Guyanaese Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_GY',
		           'country'      => 'GY',
		           'country_name' => 'Guyana'
		],
		'LRD' => [ 'name'         => 'Liberian Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'en_LR',
		           'country'      => 'LR',
		           'country_name' => 'Liberia'
		],
		'SLL' => [ 'name'         => 'Sierra Leonean Leone',
		           'symbol'       => 'Le',
		           'decimals'     => 2,
		           'locale'       => 'en_SL',
		           'country'      => 'SL',
		           'country_name' => 'Sierra Leone'
		],
		'SOS' => [ 'name'         => 'Somali Shilling',
		           'symbol'       => 'S',
		           'decimals'     => 2,
		           'locale'       => 'so_SO',
		           'country'      => 'SO',
		           'country_name' => 'Somalia'
		],
		'SRD' => [ 'name'         => 'Surinamese Dollar',
		           'symbol'       => '$',
		           'decimals'     => 2,
		           'locale'       => 'nl_SR',
		           'country'      => 'SR',
		           'country_name' => 'Suriname'
		],
		'STN' => [ 'name'         => 'São Tomé & Príncipe Dobra',
		           'symbol'       => 'Db',
		           'decimals'     => 2,
		           'locale'       => 'pt_ST',
		           'country'      => 'ST',
		           'country_name' => 'São Tomé and Príncipe'
		],
	];

	/** =========================================================================
	 *  Currency Data
	 *  ======================================================================== */

	/**
	 * Get all supported currencies.
	 *
	 * @return array All currency configurations.
	 * @since 1.0.0
	 */
	public static function all(): array {
		return self::CURRENCIES;
	}

	/**
	 * Get currency configuration.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return array|null Configuration array or null if unsupported.
	 * @since 1.0.0
	 */
	public static function get_config( string $currency ): ?array {
		return self::CURRENCIES[ strtoupper( $currency ) ] ?? null;
	}

	/**
	 * Get currency name.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string Currency name or code if not found.
	 * @since 1.0.0
	 */
	public static function get_name( string $currency ): string {
		$config = self::get_config( $currency );

		return $config['name'] ?? strtoupper( $currency );
	}

	/**
	 * Get currency symbol.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string Symbol or currency code if not found.
	 * @since 1.0.0
	 */
	public static function get_symbol( string $currency ): string {
		$config = self::get_config( $currency );

		return $config['symbol'] ?? strtoupper( $currency );
	}

	/**
	 * Get decimal places for currency.
	 *
	 * Returns the number of decimal places that Stripe's API expects for this
	 * currency. Note that some currencies (ISK, UGX) are logically zero-decimal
	 * but Stripe requires two-decimal representation.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return int Number of decimal places.
	 * @since 1.0.0
	 */
	public static function get_decimals( string $currency ): int {
		$config = self::get_config( $currency );

		return $config['decimals'] ?? 2;
	}

	/**
	 * Get the native locale for a currency.
	 *
	 * Returns the primary locale associated with the currency's home country.
	 * For locale-aware formatting, prefer format_localized() which defaults
	 * to the WordPress site locale.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string Locale string (e.g., 'en_US').
	 * @since 1.0.0
	 */
	public static function get_native_locale( string $currency ): string {
		$config = self::get_config( $currency );

		return $config['locale'] ?? 'en_US';
	}

	/**
	 * Get the ISO 3166-1 alpha-2 country code for a currency.
	 *
	 * Returns the primary country code associated with the currency.
	 * Regional currencies (EUR, XAF, XOF, XCD) return the code of
	 * their primary representative country or region.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string Country code (e.g., 'US', 'GB') or empty string if not found.
	 * @since 1.1.0
	 */
	public static function get_country( string $currency ): string {
		$config = self::get_config( $currency );

		return $config['country'] ?? '';
	}

	/**
	 * Get the country name for a currency.
	 *
	 * Returns the primary country name associated with the currency.
	 * Regional currencies return descriptive names (e.g., 'Eurozone',
	 * 'West Africa', 'Central Africa').
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string Country name or empty string if not found.
	 * @since 1.1.0
	 */
	public static function get_country_name( string $currency ): string {
		$config = self::get_config( $currency );

		return $config['country_name'] ?? '';
	}

	/**
	 * Get currency code(s) for a given country code.
	 *
	 * Performs a reverse lookup from country code to currency code(s).
	 * Most countries have a single currency, but this returns an array
	 * to handle edge cases.
	 *
	 * @param string $country ISO 3166-1 alpha-2 country code.
	 *
	 * @return array Currency codes associated with the country.
	 * @since 1.1.0
	 */
	public static function get_by_country( string $country ): array {
		$country    = strtoupper( $country );
		$currencies = [];

		foreach ( self::CURRENCIES as $code => $config ) {
			if ( strtoupper( $config['country'] ) === $country ) {
				$currencies[] = $code;
			}
		}

		return $currencies;
	}

	/**
	 * Get currencies formatted as select options.
	 *
	 * Returns currencies in "Name (symbol) — CODE" format suitable for
	 * dropdown selects.
	 *
	 * @return array<string, string> Options keyed by currency code.
	 * @since 1.0.0
	 */
	public static function get_options(): array {
		$options = [];

		foreach ( self::CURRENCIES as $code => $config ) {
			$options[ $code ] = $config['name'] . ' (' . $config['symbol'] . ') — ' . $code;
		}

		return $options;
	}

	/**
	 * Get currencies formatted as select options with country names.
	 *
	 * Returns currencies in "Name · Country — CODE" format suitable for
	 * dropdown selects, similar to the Revolut currency picker style.
	 *
	 * @return array<string, string> Options keyed by currency code.
	 * @since 1.1.0
	 */
	public static function get_country_options(): array {
		$options = [];

		foreach ( self::CURRENCIES as $code => $config ) {
			$options[ $code ] = $config['name'] . ' · ' . $config['country_name'] . ' — ' . $code;
		}

		return $options;
	}

	/**
	 * Get currency codes as select options.
	 *
	 * Returns a simple code-to-code map suitable for compact dropdowns
	 * where only the currency code is needed.
	 *
	 * @return array<string, string> Options keyed by currency code.
	 * @since 1.0.0
	 */
	public static function get_codes(): array {
		$options = [];

		foreach ( self::CURRENCIES as $code => $config ) {
			$options[ $code ] = $code;
		}

		return $options;
	}

	/** =========================================================================
	 *  Formatting
	 *  ======================================================================== */

	/**
	 * Format amount for display with currency symbol.
	 *
	 * Uses a simple symbol-prefix format suitable for admin contexts.
	 * For customer-facing locale-aware formatting, use format_localized().
	 *
	 * @param int    $amount   Amount in smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (3-letter ISO).
	 *
	 * @return string Formatted amount with symbol.
	 * @since 1.0.0
	 */
	public static function format( int $amount, string $currency ): string {
		$currency = strtoupper( $currency );
		$config   = self::get_config( $currency );

		if ( ! $config ) {
			return (string) $amount;
		}

		$is_negative = $amount < 0;
		$abs_amount  = abs( $amount );

		if ( $config['decimals'] === 0 ) {
			$formatted = number_format( $abs_amount );
		} else {
			$divisor   = pow( 10, $config['decimals'] );
			$formatted = number_format( $abs_amount / $divisor, $config['decimals'] );
		}

		return ( $is_negative ? '-' : '' ) . $config['symbol'] . $formatted;
	}

	/**
	 * Format amount using locale-aware formatting.
	 *
	 * Handles symbol position, decimal/thousands separators, and spacing
	 * according to locale conventions. Suitable for customer-facing
	 * storefront display.
	 *
	 * When no locale is provided, defaults to the WordPress site locale.
	 * Requires the PHP intl extension. Falls back to format() if unavailable.
	 *
	 * @param int    $amount   Amount in smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (3-letter ISO).
	 * @param string $locale   Optional locale override (e.g., 'de_DE').
	 *
	 * @return string Locale-formatted amount with symbol.
	 * @since 1.0.0
	 */
	public static function format_localized( int $amount, string $currency, string $locale = '' ): string {
		$currency = strtoupper( $currency );
		$config   = self::get_config( $currency );

		if ( ! $config || ! class_exists( 'NumberFormatter' ) ) {
			return self::format( $amount, $currency );
		}

		if ( empty( $locale ) ) {
			$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		}

		$decimals = $config['decimals'];
		$decimal  = $decimals > 0 ? $amount / pow( 10, $decimals ) : $amount;

		$formatter = new \NumberFormatter( $locale, \NumberFormatter::CURRENCY );
		$result    = $formatter->formatCurrency( (float) $decimal, $currency );

		return $result !== false ? $result : self::format( $amount, $currency );
	}

	/**
	 * Format amount without currency symbol.
	 *
	 * @param int    $amount   Amount in smallest unit.
	 * @param string $currency Currency code.
	 *
	 * @return string Formatted amount without symbol.
	 * @since 1.0.0
	 */
	public static function format_plain( int $amount, string $currency ): string {
		$currency = strtoupper( $currency );
		$config   = self::get_config( $currency );

		if ( ! $config ) {
			return (string) $amount;
		}

		if ( $config['decimals'] === 0 ) {
			return number_format( $amount );
		}

		$divisor = pow( 10, $config['decimals'] );

		return number_format( $amount / $divisor, $config['decimals'] );
	}

	/**
	 * Format with currency code instead of symbol.
	 *
	 * @param int    $amount   Amount in smallest unit.
	 * @param string $currency Currency code.
	 *
	 * @return string Amount with currency code (e.g., "99.99 USD").
	 * @since 1.0.0
	 */
	public static function format_with_code( int $amount, string $currency ): string {
		return self::format_plain( $amount, $currency ) . ' ' . strtoupper( $currency );
	}

	/**
	 * Render a formatted price as HTML.
	 *
	 * Wraps the formatted amount in a span for use in admin tables,
	 * order screens, or any context that needs inline HTML output.
	 *
	 * @param int    $amount   Amount in smallest unit (e.g., cents).
	 * @param string $currency Currency code.
	 *
	 * @return string HTML span with formatted price.
	 * @since 1.0.0
	 */
	public static function render( int $amount, string $currency ): string {
		return sprintf(
			'<span class="price">%s</span>',
			esc_html( self::format( $amount, $currency ) )
		);
	}

	/** =========================================================================
	 *  Unit Conversion
	 *  ======================================================================== */

	/**
	 * Convert decimal amount to the smallest unit for Stripe.
	 *
	 * @param float  $amount   Decimal amount (e.g., 19.99).
	 * @param string $currency Currency code.
	 *
	 * @return int Amount in the smallest unit.
	 * @since 1.0.0
	 */
	public static function to_smallest_unit( float $amount, string $currency ): int {
		$config   = self::get_config( $currency );
		$decimals = $config['decimals'] ?? 2;

		return (int) round( $amount * pow( 10, $decimals ) );
	}

	/**
	 * Convert from the smallest unit to decimal amount.
	 *
	 * @param int    $amount   Amount in the smallest unit.
	 * @param string $currency Currency code.
	 *
	 * @return float Decimal amount.
	 * @since 1.0.0
	 */
	public static function from_smallest_unit( int $amount, string $currency ): float {
		$config   = self::get_config( $currency );
		$decimals = $config['decimals'] ?? 2;

		if ( $decimals === 0 ) {
			return (float) $amount;
		}

		return $amount / pow( 10, $decimals );
	}

	/** =========================================================================
	 *  Validation
	 *  ======================================================================== */

	/**
	 * Check if currency is supported.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return bool True if supported.
	 * @since 1.0.0
	 */
	public static function is_supported( string $currency ): bool {
		return isset( self::CURRENCIES[ strtoupper( $currency ) ] );
	}

	/**
	 * Check if currency is zero-decimal.
	 *
	 * Zero-decimal currencies (e.g. JPY, KRW) are stored and sent to
	 * Stripe as whole numbers without any multiplication.
	 *
	 * Note: Some currencies like ISK and UGX are logically zero-decimal
	 * but Stripe requires two-decimal representation for backward
	 * compatibility. This method returns false for those currencies
	 * since the API expects two-decimal values.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return bool True if zero-decimal currency.
	 * @since 1.0.0
	 */
	public static function is_zero_decimal( string $currency ): bool {
		$config = self::get_config( $currency );

		return $config && $config['decimals'] === 0;
	}

}