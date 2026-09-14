<?php

namespace Mpdf;

class Ucdn
{

	/* HarfBuzz ucdn/unicodedata_db.h */
	/* HarfBuzz ucdn/ucdn.c */
	/* HarfBuzz ucdn/ucdn.h */

	const SCRIPT_COMMON = 0;
	const SCRIPT_LATIN = 1;
	const SCRIPT_GREEK = 2;
	const SCRIPT_CYRILLIC = 3;
	const SCRIPT_ARMENIAN = 4;
	const SCRIPT_HEBREW = 5;
	const SCRIPT_ARABIC = 6;
	const SCRIPT_SYRIAC = 7;
	const SCRIPT_THAANA = 8;
	const SCRIPT_DEVANAGARI = 9;
	const SCRIPT_BENGALI = 10;
	const SCRIPT_GURMUKHI = 11;
	const SCRIPT_GUJARATI = 12;
	const SCRIPT_ORIYA = 13;
	const SCRIPT_TAMIL = 14;
	const SCRIPT_TELUGU = 15;
	const SCRIPT_KANNADA = 16;
	const SCRIPT_MALAYALAM = 17;
	const SCRIPT_SINHALA = 18;
	const SCRIPT_THAI = 19;
	const SCRIPT_LAO = 20;
	const SCRIPT_TIBETAN = 21;
	const SCRIPT_MYANMAR = 22;
	const SCRIPT_GEORGIAN = 23;
	const SCRIPT_HANGUL = 24;
	const SCRIPT_ETHIOPIC = 25;
	const SCRIPT_CHEROKEE = 26;
	const SCRIPT_CANADIAN_ABORIGINAL = 27;
	const SCRIPT_OGHAM = 28;
	const SCRIPT_RUNIC = 29;
	const SCRIPT_KHMER = 30;
	const SCRIPT_MONGOLIAN = 31;
	const SCRIPT_HIRAGANA = 32;
	const SCRIPT_KATAKANA = 33;
	const SCRIPT_BOPOMOFO = 34;
	const SCRIPT_HAN = 35;
	const SCRIPT_YI = 36;
	const SCRIPT_OLD_ITALIC = 37;
	const SCRIPT_GOTHIC = 38;
	const SCRIPT_DESERET = 39;
	const SCRIPT_INHERITED = 40;
	const SCRIPT_TAGALOG = 41;
	const SCRIPT_HANUNOO = 42;
	const SCRIPT_BUHID = 43;
	const SCRIPT_TAGBANWA = 44;
	const SCRIPT_LIMBU = 45;
	const SCRIPT_TAI_LE = 46;
	const SCRIPT_LINEAR_B = 47;
	const SCRIPT_UGARITIC = 48;
	const SCRIPT_SHAVIAN = 49;
	const SCRIPT_OSMANYA = 50;
	const SCRIPT_CYPRIOT = 51;
	const SCRIPT_BRAILLE = 52;
	const SCRIPT_BUGINESE = 53;
	const SCRIPT_COPTIC = 54;
	const SCRIPT_NEW_TAI_LUE = 55;
	const SCRIPT_GLAGOLITIC = 56;
	const SCRIPT_TIFINAGH = 57;
	const SCRIPT_SYLOTI_NAGRI = 58;
	const SCRIPT_OLD_PERSIAN = 59;
	const SCRIPT_KHAROSHTHI = 60;
	const SCRIPT_BALINESE = 61;
	const SCRIPT_CUNEIFORM = 62;
	const SCRIPT_PHOENICIAN = 63;
	const SCRIPT_PHAGS_PA = 64;
	const SCRIPT_NKO = 65;
	const SCRIPT_SUNDANESE = 66;
	const SCRIPT_LEPCHA = 67;
	const SCRIPT_OL_CHIKI = 68;
	const SCRIPT_VAI = 69;
	const SCRIPT_SAURASHTRA = 70;
	const SCRIPT_KAYAH_LI = 71;
	const SCRIPT_REJANG = 72;
	const SCRIPT_LYCIAN = 73;
	const SCRIPT_CARIAN = 74;
	const SCRIPT_LYDIAN = 75;
	const SCRIPT_CHAM = 76;
	const SCRIPT_TAI_THAM = 77;
	const SCRIPT_TAI_VIET = 78;
	const SCRIPT_AVESTAN = 79;
	const SCRIPT_EGYPTIAN_HIEROGLYPHS = 80;
	const SCRIPT_SAMARITAN = 81;
	const SCRIPT_LISU = 82;
	const SCRIPT_BAMUM = 83;
	const SCRIPT_JAVANESE = 84;
	const SCRIPT_MEETEI_MAYEK = 85;
	const SCRIPT_IMPERIAL_ARAMAIC = 86;
	const SCRIPT_OLD_SOUTH_ARABIAN = 87;
	const SCRIPT_INSCRIPTIONAL_PARTHIAN = 88;
	const SCRIPT_INSCRIPTIONAL_PAHLAVI = 89;
	const SCRIPT_OLD_TURKIC = 90;
	const SCRIPT_KAITHI = 91;
	const SCRIPT_BATAK = 92;
	const SCRIPT_BRAHMI = 93;
	const SCRIPT_MANDAIC = 94;
	const SCRIPT_CHAKMA = 95;
	const SCRIPT_MEROITIC_CURSIVE = 96;
	const SCRIPT_MEROITIC_HIEROGLYPHS = 97;
	const SCRIPT_MIAO = 98;
	const SCRIPT_SHARADA = 99;
	const SCRIPT_SORA_SOMPENG = 100;
	const SCRIPT_TAKRI = 101;
	const SCRIPT_UNKNOWN = 102;
	const SCRIPT_ADLAM = 103;
	const SCRIPT_AHOM = 104;
	const SCRIPT_ANATOLIAN_HIEROGLYPHS = 105;
	const SCRIPT_BASSA_VAH = 106;
	const SCRIPT_BERIA_ERFE = 107;
	const SCRIPT_BHAIKSUKI = 108;
	const SCRIPT_CAUCASIAN_ALBANIAN = 109;
	const SCRIPT_CHORASMIAN = 110;
	const SCRIPT_CYPRO_MINOAN = 111;
	const SCRIPT_DIVES_AKURU = 112;
	const SCRIPT_DOGRA = 113;
	const SCRIPT_DUPLOYAN = 114;
	const SCRIPT_ELBASAN = 115;
	const SCRIPT_ELYMAIC = 116;
	const SCRIPT_GARAY = 117;
	const SCRIPT_GRANTHA = 118;
	const SCRIPT_GUNJALA_GONDI = 119;
	const SCRIPT_GURUNG_KHEMA = 120;
	const SCRIPT_HANIFI_ROHINGYA = 121;
	const SCRIPT_HATRAN = 122;
	const SCRIPT_KAWI = 123;
	const SCRIPT_KHITAN_SMALL_SCRIPT = 124;
	const SCRIPT_KHOJKI = 125;
	const SCRIPT_KHUDAWADI = 126;
	const SCRIPT_KIRAT_RAI = 127;
	const SCRIPT_LINEAR_A = 128;
	const SCRIPT_MAHAJANI = 129;
	const SCRIPT_MAKASAR = 130;
	const SCRIPT_MANICHAEAN = 131;
	const SCRIPT_MARCHEN = 132;
	const SCRIPT_MASARAM_GONDI = 133;
	const SCRIPT_MEDEFAIDRIN = 134;
	const SCRIPT_MENDE_KIKAKUI = 135;
	const SCRIPT_MODI = 136;
	const SCRIPT_MRO = 137;
	const SCRIPT_MULTANI = 138;
	const SCRIPT_NABATAEAN = 139;
	const SCRIPT_NAG_MUNDARI = 140;
	const SCRIPT_NANDINAGARI = 141;
	const SCRIPT_NEWA = 142;
	const SCRIPT_NUSHU = 143;
	const SCRIPT_NYIAKENG_PUACHUE_HMONG = 144;
	const SCRIPT_OL_ONAL = 145;
	const SCRIPT_OLD_HUNGARIAN = 146;
	const SCRIPT_OLD_NORTH_ARABIAN = 147;
	const SCRIPT_OLD_PERMIC = 148;
	const SCRIPT_OLD_SOGDIAN = 149;
	const SCRIPT_OLD_UYGHUR = 150;
	const SCRIPT_OSAGE = 151;
	const SCRIPT_PAHAWH_HMONG = 152;
	const SCRIPT_PALMYRENE = 153;
	const SCRIPT_PAU_CIN_HAU = 154;
	const SCRIPT_PSALTER_PAHLAVI = 155;
	const SCRIPT_SIDDHAM = 156;
	const SCRIPT_SIDETIC = 157;
	const SCRIPT_SIGNWRITING = 158;
	const SCRIPT_SOGDIAN = 159;
	const SCRIPT_SOYOMBO = 160;
	const SCRIPT_SUNUWAR = 161;
	const SCRIPT_TAI_YO = 162;
	const SCRIPT_TANGSA = 163;
	const SCRIPT_TANGUT = 164;
	const SCRIPT_TIRHUTA = 165;
	const SCRIPT_TODHRI = 166;
	const SCRIPT_TOLONG_SIKI = 167;
	const SCRIPT_TOTO = 168;
	const SCRIPT_TULU_TIGALARI = 169;
	const SCRIPT_VITHKUQI = 170;
	const SCRIPT_WANCHO = 171;
	const SCRIPT_WARANG_CITI = 172;
	const SCRIPT_YEZIDI = 173;
	const SCRIPT_ZANABAZAR_SQUARE = 174;

	public static function get_ucd_record($code)
	{
		if ($code >= 0x110000) {
			$index = 0;
		} else {
			$index = self::$index0[$code >> (8)] << 5;
			$offset = ($code >> 3) & ((1 << 5) - 1);
			$index = self::$index1[$index + $offset] << 3;
			$offset = $code & ((1 << 3) - 1);
			$index = self::$index2[$index + $offset];
		}
		return self::$ucd_records[$index];
	}

	public static function get_general_category($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[0];
	}

	public static function get_combining_class($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[1];
	}

	public static function get_bidi_class($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[2];
	}

	public static function get_mirrored($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[3];
	}

	public static function get_east_asian_width($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[4];
	}

	public static function get_normalization_check($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[5];
	}

	public static function get_script($code)
	{
		$ucd_record = self::get_ucd_record($code);
		return $ucd_record[6];
	}

	// mPDF added
	public static $uni_scriptblock = [
		/* SCRIPT_COMMON */ 0 => '',
		/* SCRIPT_LATIN */ 1 => 'latn',
		/* SCRIPT_GREEK */ 2 => 'grek',
		/* SCRIPT_CYRILLIC */ 3 => 'cyrl',
		/* SCRIPT_ARMENIAN */ 4 => 'armn',
		/* SCRIPT_HEBREW */ 5 => 'hebr',
		/* SCRIPT_ARABIC */ 6 => 'arab',
		/* SCRIPT_SYRIAC */ 7 => 'syrc',
		/* SCRIPT_THAANA */ 8 => 'thaa',
		/* SCRIPT_DEVANAGARI */ 9 => 'dev2',
		/* SCRIPT_BENGALI */ 10 => 'bng2',
		/* SCRIPT_GURMUKHI */ 11 => 'gur2',
		/* SCRIPT_GUJARATI */ 12 => 'gjr2',
		/* SCRIPT_ORIYA */ 13 => 'ory2',
		/* SCRIPT_TAMIL */ 14 => 'tml2',
		/* SCRIPT_TELUGU */ 15 => 'tel2',
		/* SCRIPT_KANNADA */ 16 => 'knd2',
		/* SCRIPT_MALAYALAM */ 17 => 'mlm2',
		/* SCRIPT_SINHALA */ 18 => 'sinh',
		/* SCRIPT_THAI */ 19 => 'thai',
		/* SCRIPT_LAO */ 20 => 'lao ',
		/* SCRIPT_TIBETAN */ 21 => 'tibt',
		/* SCRIPT_MYANMAR */ 22 => 'mym2',
		/* SCRIPT_GEORGIAN */ 23 => 'geor',
		/* SCRIPT_HANGUL */ 24 => 'jamo', /* there is also a hang tag, but we want to activate jamo features if present */
		/* SCRIPT_ETHIOPIC */ 25 => 'ethi',
		/* SCRIPT_CHEROKEE */ 26 => 'cher',
		/* SCRIPT_CANADIAN_ABORIGINAL */ 27 => 'cans',
		/* SCRIPT_OGHAM */ 28 => 'ogam',
		/* SCRIPT_RUNIC */ 29 => 'runr',
		/* SCRIPT_KHMER */ 30 => 'khmr',
		/* SCRIPT_MONGOLIAN */ 31 => 'mong',
		/* SCRIPT_HIRAGANA */ 32 => 'kana',
		/* SCRIPT_KATAKANA */ 33 => 'kana',
		/* SCRIPT_BOPOMOFO */ 34 => 'bopo',
		/* SCRIPT_HAN */ 35 => 'hani',
		/* SCRIPT_YI */ 36 => 'yi  ',
		/* SCRIPT_OLD_ITALIC */ 37 => 'ital',
		/* SCRIPT_GOTHIC */ 38 => 'goth',
		/* SCRIPT_DESERET */ 39 => 'dsrt',
		/* SCRIPT_INHERITED */ 40 => '',
		/* SCRIPT_TAGALOG */ 41 => 'tglg',
		/* SCRIPT_HANUNOO */ 42 => 'hano',
		/* SCRIPT_BUHID */ 43 => 'buhd',
		/* SCRIPT_TAGBANWA */ 44 => 'tagb',
		/* SCRIPT_LIMBU */ 45 => 'limb',
		/* SCRIPT_TAI_LE */ 46 => 'tale',
		/* SCRIPT_LINEAR_B */ 47 => 'linb',
		/* SCRIPT_UGARITIC */ 48 => 'ugar',
		/* SCRIPT_SHAVIAN */ 49 => 'shaw',
		/* SCRIPT_OSMANYA */ 50 => 'osma',
		/* SCRIPT_CYPRIOT */ 51 => 'cprt',
		/* SCRIPT_BRAILLE */ 52 => 'brai',
		/* SCRIPT_BUGINESE */ 53 => 'bugi',
		/* SCRIPT_COPTIC */ 54 => 'copt',
		/* SCRIPT_NEW_TAI_LUE */ 55 => 'talu',
		/* SCRIPT_GLAGOLITIC */ 56 => 'glag',
		/* SCRIPT_TIFINAGH */ 57 => 'tfng',
		/* SCRIPT_SYLOTI_NAGRI */ 58 => 'sylo',
		/* SCRIPT_OLD_PERSIAN */ 59 => 'xpeo',
		/* SCRIPT_KHAROSHTHI */ 60 => 'khar',
		/* SCRIPT_BALINESE */ 61 => 'bali',
		/* SCRIPT_CUNEIFORM */ 62 => 'xsux',
		/* SCRIPT_PHOENICIAN */ 63 => 'phnx',
		/* SCRIPT_PHAGS_PA */ 64 => 'phag',
		/* SCRIPT_NKO */ 65 => 'nko ',
		/* SCRIPT_SUNDANESE */ 66 => 'sund',
		/* SCRIPT_LEPCHA */ 67 => 'lepc',
		/* SCRIPT_OL_CHIKI */ 68 => 'olck',
		/* SCRIPT_VAI */ 69 => 'vai ',
		/* SCRIPT_SAURASHTRA */ 70 => 'saur',
		/* SCRIPT_KAYAH_LI */ 71 => 'kali',
		/* SCRIPT_REJANG */ 72 => 'rjng',
		/* SCRIPT_LYCIAN */ 73 => 'lyci',
		/* SCRIPT_CARIAN */ 74 => 'cari',
		/* SCRIPT_LYDIAN */ 75 => 'lydi',
		/* SCRIPT_CHAM */ 76 => 'cham',
		/* SCRIPT_TAI_THAM */ 77 => 'lana',
		/* SCRIPT_TAI_VIET */ 78 => 'tavt',
		/* SCRIPT_AVESTAN */ 79 => 'avst',
		/* SCRIPT_EGYPTIAN_HIEROGLYPHS */ 80 => 'egyp',
		/* SCRIPT_SAMARITAN */ 81 => 'samr',
		/* SCRIPT_LISU */ 82 => 'lisu',
		/* SCRIPT_BAMUM */ 83 => 'bamu',
		/* SCRIPT_JAVANESE */ 84 => 'java',
		/* SCRIPT_MEETEI_MAYEK */ 85 => 'mtei',
		/* SCRIPT_IMPERIAL_ARAMAIC */ 86 => 'armi',
		/* SCRIPT_OLD_SOUTH_ARABIAN */ 87 => 'sarb',
		/* SCRIPT_INSCRIPTIONAL_PARTHIAN */ 88 => 'prti',
		/* SCRIPT_INSCRIPTIONAL_PAHLAVI */ 89 => 'phli',
		/* SCRIPT_OLD_TURKIC */ 90 => 'orkh',
		/* SCRIPT_KAITHI */ 91 => 'kthi',
		/* SCRIPT_BATAK */ 92 => 'batk',
		/* SCRIPT_BRAHMI */ 93 => 'brah',
		/* SCRIPT_MANDAIC */ 94 => 'mand',
		/* SCRIPT_CHAKMA */ 95 => 'cakm',
		/* SCRIPT_MEROITIC_CURSIVE */ 96 => 'merc',
		/* SCRIPT_MEROITIC_HIEROGLYPHS */ 97 => 'mero',
		/* SCRIPT_MIAO */ 98 => 'plrd',
		/* SCRIPT_SHARADA */ 99 => 'shrd',
		/* SCRIPT_SORA_SOMPENG */ 100 => 'sora',
		/* SCRIPT_TAKRI */ 101 => 'takr',
		/* SCRIPT_UNKNOWN */ 102 => '',
		/* SCRIPT_ADLAM */ 103 => 'adlm',
		/* SCRIPT_AHOM */ 104 => 'ahom',
		/* SCRIPT_ANATOLIAN_HIEROGLYPHS */ 105 => 'hluw',
		/* SCRIPT_BASSA_VAH */ 106 => 'bass',
		/* SCRIPT_BERIA_ERFE */ 107 => 'berf',
		/* SCRIPT_BHAIKSUKI */ 108 => 'bhks',
		/* SCRIPT_CAUCASIAN_ALBANIAN */ 109 => 'aghb',
		/* SCRIPT_CHORASMIAN */ 110 => 'chrs',
		/* SCRIPT_CYPRO_MINOAN */ 111 => 'cpmn',
		/* SCRIPT_DIVES_AKURU */ 112 => 'diak',
		/* SCRIPT_DOGRA */ 113 => 'dogr',
		/* SCRIPT_DUPLOYAN */ 114 => 'dupl',
		/* SCRIPT_ELBASAN */ 115 => 'elba',
		/* SCRIPT_ELYMAIC */ 116 => 'elym',
		/* SCRIPT_GARAY */ 117 => 'gara',
		/* SCRIPT_GRANTHA */ 118 => 'gran',
		/* SCRIPT_GUNJALA_GONDI */ 119 => 'gong',
		/* SCRIPT_GURUNG_KHEMA */ 120 => 'gukh',
		/* SCRIPT_HANIFI_ROHINGYA */ 121 => 'rohg',
		/* SCRIPT_HATRAN */ 122 => 'hatr',
		/* SCRIPT_KAWI */ 123 => 'kawi',
		/* SCRIPT_KHITAN_SMALL_SCRIPT */ 124 => 'kits',
		/* SCRIPT_KHOJKI */ 125 => 'khoj',
		/* SCRIPT_KHUDAWADI */ 126 => 'sind',
		/* SCRIPT_KIRAT_RAI */ 127 => 'krai',
		/* SCRIPT_LINEAR_A */ 128 => 'lina',
		/* SCRIPT_MAHAJANI */ 129 => 'mahj',
		/* SCRIPT_MAKASAR */ 130 => 'maka',
		/* SCRIPT_MANICHAEAN */ 131 => 'mani',
		/* SCRIPT_MARCHEN */ 132 => 'marc',
		/* SCRIPT_MASARAM_GONDI */ 133 => 'gonm',
		/* SCRIPT_MEDEFAIDRIN */ 134 => 'medf',
		/* SCRIPT_MENDE_KIKAKUI */ 135 => 'mend',
		/* SCRIPT_MODI */ 136 => 'modi',
		/* SCRIPT_MRO */ 137 => 'mroo',
		/* SCRIPT_MULTANI */ 138 => 'mult',
		/* SCRIPT_NABATAEAN */ 139 => 'nbat',
		/* SCRIPT_NAG_MUNDARI */ 140 => 'nagm',
		/* SCRIPT_NANDINAGARI */ 141 => 'nand',
		/* SCRIPT_NEWA */ 142 => 'newa',
		/* SCRIPT_NUSHU */ 143 => 'nshu',
		/* SCRIPT_NYIAKENG_PUACHUE_HMONG */ 144 => 'hmnp',
		/* SCRIPT_OL_ONAL */ 145 => 'onao',
		/* SCRIPT_OLD_HUNGARIAN */ 146 => 'hung',
		/* SCRIPT_OLD_NORTH_ARABIAN */ 147 => 'narb',
		/* SCRIPT_OLD_PERMIC */ 148 => 'perm',
		/* SCRIPT_OLD_SOGDIAN */ 149 => 'sogo',
		/* SCRIPT_OLD_UYGHUR */ 150 => 'ougr',
		/* SCRIPT_OSAGE */ 151 => 'osge',
		/* SCRIPT_PAHAWH_HMONG */ 152 => 'hmng',
		/* SCRIPT_PALMYRENE */ 153 => 'palm',
		/* SCRIPT_PAU_CIN_HAU */ 154 => 'pauc',
		/* SCRIPT_PSALTER_PAHLAVI */ 155 => 'phlp',
		/* SCRIPT_SIDDHAM */ 156 => 'sidd',
		/* SCRIPT_SIDETIC */ 157 => 'sidt',
		/* SCRIPT_SIGNWRITING */ 158 => 'sgnw',
		/* SCRIPT_SOGDIAN */ 159 => 'sogd',
		/* SCRIPT_SOYOMBO */ 160 => 'soyo',
		/* SCRIPT_SUNUWAR */ 161 => 'sunu',
		/* SCRIPT_TAI_YO */ 162 => 'tayo',
		/* SCRIPT_TANGSA */ 163 => 'tnsa',
		/* SCRIPT_TANGUT */ 164 => 'tang',
		/* SCRIPT_TIRHUTA */ 165 => 'tirh',
		/* SCRIPT_TODHRI */ 166 => 'todr',
		/* SCRIPT_TOLONG_SIKI */ 167 => 'tols',
		/* SCRIPT_TOTO */ 168 => 'toto',
		/* SCRIPT_TULU_TIGALARI */ 169 => 'tutg',
		/* SCRIPT_VITHKUQI */ 170 => 'vith',
		/* SCRIPT_WANCHO */ 171 => 'wcho',
		/* SCRIPT_WARANG_CITI */ 172 => 'wara',
		/* SCRIPT_YEZIDI */ 173 => 'yezi',
		/* SCRIPT_ZANABAZAR_SQUARE */ 174 => 'zanb',
	];

	public static $ot_languages = [
		'aa' => 'AFR ', /* Afar */
		'ab' => 'ABK ', /* Abkhazian */
		'abq' => 'ABA ', /* Abaza */
		'ada' => 'DNG ', /* Dangme */
		'ady' => 'ADY ', /* Adyghe */
		'af' => 'AFK ', /* Afrikaans */
		'aii' => 'SWA ', /* Swadaya Aramaic */
		'aiw' => 'ARI ', /* Aari */
		'alt' => 'ALT ', /* [Southern] Altai */
		'am' => 'AMH ', /* Amharic */
		'amf' => 'HBN ', /* Hammer-Banna */
		'ar' => 'ARA ', /* Arabic */
		'arn' => 'MAP ', /* Mapudungun */
		'as' => 'ASM ', /* Assamese */
		'ath' => 'ATH ', /* Athapaskan [family] */
		'atv' => 'ALT ', /* [Northern] Altai */
		'av' => 'AVR ', /* Avaric */
		'awa' => 'AWA ', /* Awadhi */
		'ay' => 'AYM ', /* Aymara */
		'az' => 'AZE ', /* Azerbaijani */
		'ba' => 'BSH ', /* Bashkir */
		'bai' => 'BML ', /* Bamileke [family] */
		'bal' => 'BLI ', /* Baluchi */
		'bci' => 'BAU ', /* Baule */
		'bcq' => 'BCH ', /* Bench */
		'be' => 'BEL ', /* Belarussian */
		'bem' => 'BEM ', /* Bemba (Zambia) */
		'ber' => 'BER ', /* Berber [family] */
		'bfq' => 'BAD ', /* Badaga */
		'bft' => 'BLT ', /* Balti */
		'bfy' => 'BAG ', /* Baghelkhandi */
		'bg' => 'BGR ', /* Bulgarian */
		'bhb' => 'BHI ', /* Bhili */
		'bho' => 'BHO ', /* Bhojpuri */
		'bik' => 'BIK ', /* Bikol */
		'bin' => 'EDO ', /* Bini */
		'bjt' => 'BLN ', /* Balanta-Ganja */
		'bla' => 'BKF ', /* Blackfoot */
		'ble' => 'BLN ', /* Balanta-Kentohe */
		'bm' => 'BMB ', /* Bambara */
		'bn' => 'BEN ', /* Bengali */
		'bo' => 'TIB ', /* Tibetan */
		'br' => 'BRE ', /* Breton */
		'bra' => 'BRI ', /* Braj Bhasha */
		'brh' => 'BRH ', /* Brahui */
		'bs' => 'BOS ', /* Bosnian */
		'btb' => 'BTI ', /* Beti (Cameroon) */
		'bxr' => 'RBU ', /* Russian Buriat */
		'byn' => 'BIL ', /* Bilen */
		'ca' => 'CAT ', /* Catalan */
		'ce' => 'CHE ', /* Chechen */
		'ceb' => 'CEB ', /* Cebuano */
		'chp' => 'CHP ', /* Chipewyan */
		'chr' => 'CHR ', /* Cherokee */
		'ckt' => 'CHK ', /* Chukchi */
		'cop' => 'COP ', /* Coptic */
		'cr' => 'CRE ', /* Cree */
		'crh' => 'CRT ', /* Crimean Tatar */
		'crj' => 'ECR ', /* [Southern] East Cree */
		'crl' => 'ECR ', /* [Northern] East Cree */
		'crm' => 'MCR ', /* Moose Cree */
		'crx' => 'CRR ', /* Carrier */
		'cs' => 'CSY ', /* Czech */
		'cu' => 'CSL ', /* Church Slavic */
		'cv' => 'CHU ', /* Chuvash */
		'cwd' => 'DCR ', /* Woods Cree */
		'cy' => 'WEL ', /* Welsh */
		'da' => 'DAN ', /* Danish */
		'dap' => 'NIS ', /* Nisi (India) */
		'dar' => 'DAR ', /* Dargwa */
		'de' => 'DEU ', /* German */
		'din' => 'DNK ', /* Dinka */
		'dje' => 'DJR ', /* Djerma */
		'dng' => 'DUN ', /* Dungan */
		'doi' => 'DGR ', /* Dogri */
		'dsb' => 'LSB ', /* Lower Sorbian */
		'dv' => 'DIV ', /* Dhivehi */
		'dyu' => 'JUL ', /* Jula */
		'dz' => 'DZN ', /* Dzongkha */
		'ee' => 'EWE ', /* Ewe */
		'efi' => 'EFI ', /* Efik */
		'el' => 'ELL ', /* Modern Greek (1453-) */
		'grc' => 'PGR ', /* Polytonic Greek */
		'en' => 'ENG ', /* English */
		'eo' => 'NTO ', /* Esperanto */
		'eot' => 'BTI ', /* Beti (Côte d'Ivoire) */
		'es' => 'ESP ', /* Spanish */
		'et' => 'ETI ', /* Estonian */
		'eu' => 'EUQ ', /* Basque */
		'eve' => 'EVN ', /* Even */
		'evn' => 'EVK ', /* Evenki */
		'fa' => 'FAR ', /* Persian */
		'ff' => 'FUL ', /* Fulah */
		'fi' => 'FIN ', /* Finnish */
		'fil' => 'PIL ', /* Filipino */
		'fj' => 'FJI ', /* Fijian */
		'fo' => 'FOS ', /* Faroese */
		'fon' => 'FON ', /* Fon */
		'fr' => 'FRA ', /* French */
		'fur' => 'FRL ', /* Friulian */
		'fy' => 'FRI ', /* Western Frisian */
		'ga' => 'IRI ', /* Irish */
		'gaa' => 'GAD ', /* Ga */
		'gag' => 'GAG ', /* Gagauz */
		'gbm' => 'GAW ', /* Garhwali */
		'gd' => 'GAE ', /* Scottish Gaelic */
		'gez' => 'GEZ ', /* Ge'ez */
		'gl' => 'GAL ', /* Galician */
		'gld' => 'NAN ', /* Nanai */
		'gn' => 'GUA ', /* Guarani */
		'gon' => 'GON ', /* Gondi */
		'grt' => 'GRO ', /* Garo */
		'gru' => 'SOG ', /* Sodo Gurage */
		'gu' => 'GUJ ', /* Gujarati */
		'guk' => 'GMZ ', /* Gumuz */
		'gv' => 'MNX ', /* Manx Gaelic */
		'ha' => 'HAU ', /* Hausa */
		'har' => 'HRI ', /* Harari */
		'haw' => 'HAW ', /* Hawaiin */
		'he' => 'IWR ', /* Hebrew */
		'hi' => 'HIN ', /* Hindi */
		'hil' => 'HIL ', /* Hiligaynon */
		'hnd' => 'HND ', /* [Southern] Hindko */
		'hne' => 'CHH ', /* Chattisgarhi */
		'hno' => 'HND ', /* [Northern] Hindko */
		'hoc' => 'HO  ', /* Ho */
		'hoj' => 'HAR ', /* Harauti */
		'hr' => 'HRV ', /* Croatian */
		'hsb' => 'USB ', /* Upper Sorbian */
		'ht' => 'HAI ', /* Haitian */
		'hu' => 'HUN ', /* Hungarian */
		'hy' => 'HYE ', /* Armenian */
		'id' => 'IND ', /* Indonesian */
		'ig' => 'IBO ', /* Igbo */
		'igb' => 'EBI ', /* Ebira */
		'ijo' => 'IJO ', /* Ijo [family] */
		'ilo' => 'ILO ', /* Ilokano */
		'inh' => 'ING ', /* Ingush */
		'is' => 'ISL ', /* Icelandic */
		'it' => 'ITA ', /* Italian */
		'iu' => 'INU ', /* Inuktitut */
		'ja' => 'JAN ', /* Japanese */
		'jv' => 'JAV ', /* Javanese */
		'ka' => 'KAT ', /* Georgian */
		'kaa' => 'KRK ', /* Karakalpak */
		'kam' => 'KMB ', /* Kamba (Kenya) */
		'kar' => 'KRN ', /* Karen [family] */
		'kbd' => 'KAB ', /* Kabardian */
		'kdr' => 'KRM ', /* Karaim */
		'kdt' => 'KUY ', /* Kuy */
		'kex' => 'KKN ', /* Kokni */
		'kfr' => 'KAC ', /* Kachchi */
		'kfy' => 'KMN ', /* Kumaoni */
		'kha' => 'KSI ', /* Khasi */
		'khb' => 'XBD ', /* Tai Lue */
		'khw' => 'KHW ', /* Khowar */
		'ki' => 'KIK ', /* Kikuyu */
		'kjh' => 'KHA ', /* Khakass */
		'kk' => 'KAZ ', /* Kazakh */
		'kl' => 'GRN ', /* Kalaallisut */
		'kln' => 'KAL ', /* Kalenjin */
		'km' => 'KHM ', /* Central Khmer */
		'kmb' => 'MBN ', /* [North] Mbundu */
		'kmw' => 'KMO ', /* Komo (Democratic Republic of Congo) */
		'kn' => 'KAN ', /* Kannada */
		'ko' => 'KOR ', /* Korean */
		'koi' => 'KOP ', /* Komi-Permyak */
		'kok' => 'KOK ', /* Konkani */
		'kpe' => 'KPL ', /* Kpelle */
		'kpv' => 'KOZ ', /* Komi-Zyrian */
		'kpy' => 'KYK ', /* Koryak */
		'kqy' => 'KRT ', /* Koorete */
		'kr' => 'KNR ', /* Kanuri */
		'kri' => 'KRI ', /* Krio */
		'krl' => 'KRL ', /* Karelian */
		'kru' => 'KUU ', /* Kurukh */
		'ks' => 'KSH ', /* Kashmiri */
		'ku' => 'KUR ', /* Kurdish */
		'kum' => 'KUM ', /* Kumyk */
		'kvd' => 'KUI ', /* Kui (Indonesia) */
		'kxc' => 'KMS ', /* Komso */
		'kxu' => 'KUI ', /* Kui (India) */
		'ky' => 'KIR ', /* Kirghiz */
		'la' => 'LAT ', /* Latin */
		'lad' => 'JUD ', /* Ladino */
		'lb' => 'LTZ ', /* Luxembourgish */
		'lbe' => 'LAK ', /* Lak */
		'lbj' => 'LDK ', /* Ladakhi */
		'lez' => 'LEZ ', /* Lezgi */
		'lg' => 'LUG ', /* Luganda */
		'lif' => 'LMB ', /* Limbu */
		'lld' => 'LAD ', /* Ladin */
		'lmn' => 'LAM ', /* Lambani */
		'ln' => 'LIN ', /* Lingala */
		'lo' => 'LAO ', /* Lao */
		'lt' => 'LTH ', /* Lithuanian */
		'lu' => 'LUB ', /* Luba-Katanga */
		'lua' => 'LUB ', /* Luba-Kasai */
		'luo' => 'LUO ', /* Luo (Kenya and Tanzania) */
		'lus' => 'MIZ ', /* Mizo */
		'luy' => 'LUH ', /* Luhya [macrolanguage] */
		'lv' => 'LVI ', /* Latvian */
		'lzz' => 'LAZ ', /* Laz */
		'mai' => 'MTH ', /* Maithili */
		'mdc' => 'MLE ', /* Male (Papua New Guinea) */
		'mdf' => 'MOK ', /* Moksha */
		'mdy' => 'MLE ', /* Male (Ethiopia) */
		'men' => 'MDE ', /* Mende (Sierra Leone) */
		'mg' => 'MLG ', /* Malagasy */
		'mhr' => 'LMA ', /* Low Mari */
		'mi' => 'MRI ', /* Maori */
		'mk' => 'MKD ', /* Macedonian */
		'ml' => 'MLR ', /* Malayalam reformed  (MAL is Malayalam Traditional) */
		'mn' => 'MNG ', /* Mongolian */
		'mnc' => 'MCH ', /* Manchu */
		'mni' => 'MNI ', /* Manipuri */
		'mnk' => 'MND ', /* Mandinka */
		'mns' => 'MAN ', /* Mansi */
		'mnw' => 'MON ', /* Mon */
		'mo' => 'MOL ', /* Moldavian */
		'moh' => 'MOH ', /* Mohawk */
		'mpe' => 'MAJ ', /* Majang */
		'mr' => 'MAR ', /* Marathi */
		'mrj' => 'HMA ', /* High Mari */
		'ms' => 'MLY ', /* Malay */
		'mt' => 'MTS ', /* Maltese */
		'mwr' => 'MAW ', /* Marwari */
		'my' => 'BRM ', /* Burmese */
		'mym' => 'MEN ', /* Me'en */
		'myv' => 'ERZ ', /* Erzya */
		'nag' => 'NAG ', /* Naga-Assamese */
		'nb' => 'NOR ', /* Norwegian Bokmål */
		'nco' => 'SIB ', /* Sibe */
		'nd' => 'NDB ', /* [North] Ndebele */
		'ne' => 'NEP ', /* Nepali */
		'new' => 'NEW ', /* Newari */
		'ng' => 'NDG ', /* Ndonga */
		'ngl' => 'LMW ', /* Lomwe */
		'niu' => 'NIU ', /* Niuean */
		'niv' => 'GIL ', /* Gilyak */
		'nl' => 'NLD ', /* Dutch */
		'nn' => 'NYN ', /* Norwegian Nynorsk */
		'no' => 'NOR ', /* Norwegian (deprecated) */
		'nod' => 'NTA ', /* Northern Tai */
		'nog' => 'NOG ', /* Nogai */
		'nqo' => 'NKO ', /* N'Ko */
		'nr' => 'NDB ', /* [South] Ndebele */
		'nsk' => 'NAS ', /* Naskapi */
		'nso' => 'SOT ', /* [Northern] Sotho */
		'ny' => 'CHI ', /* Nyanja */
		'nyn' => 'NKL ', /* Nkole */
		'oc' => 'OCI ', /* Occitan (post 1500) */
		'oj' => 'OJB ', /* Ojibwa */
		'ojs' => 'OCR ', /* Oji-Cree */
		'om' => 'ORO ', /* Oromo */
		'or' => 'ORI ', /* Oriya */
		'os' => 'OSS ', /* Ossetian */
		'pa' => 'PAN ', /* Panjabi */
		'pce' => 'PLG ', /* [Ruching] Palaung */
		'pi' => 'PAL ', /* Pali */
		'pl' => 'PLK ', /* Polish */
		'pll' => 'PLG ', /* [Shwe] Palaung */
		'plp' => 'PAP ', /* Palpa */
		'prs' => 'DRI ', /* Dari */
		'ps' => 'PAS ', /* Pushto */
		'pt' => 'PTG ', /* Portuguese */
		'raj' => 'RAJ ', /* Rajasthani */
		'rbb' => 'PLG ', /* [Rumai] Palaung */
		'ria' => 'RIA ', /* Riang (India) */
		'ril' => 'RIA ', /* Riang (Myanmar) */
		'rki' => 'ARK ', /* Arakanese */
		'rm' => 'RMS ', /* Rhaeto-Romanic */
		'ro' => 'ROM ', /* Romanian */
		'rom' => 'ROY ', /* Romany */
		'ru' => 'RUS ', /* Russian */
		'rue' => 'RSY ', /* Rusyn */
		'rw' => 'RUA ', /* Ruanda */
		'sa' => 'SAN ', /* Sanskrit */
		'sah' => 'YAK ', /* Yakut */
		'sat' => 'SAT ', /* Santali */
		'sck' => 'SAD ', /* Sadri */
		'scs' => 'SLA ', /* [North] Slavey */
		'sd' => 'SND ', /* Sindhi */
		'se' => 'NSM ', /* Northern Sami */
		'seh' => 'SNA ', /* Sena */
		'sel' => 'SEL ', /* Selkup */
		'sg' => 'SGO ', /* Sango */
		'shn' => 'SHN ', /* Shan */
		'si' => 'SNH ', /* Sinhala */
		'sid' => 'SID ', /* Sidamo */
		'sjd' => 'KSM ', /* Kildin Sami */
		'sk' => 'SKY ', /* Slovak */
		'skr' => 'SRK ', /* Seraiki */
		'sl' => 'SLV ', /* Slovenian */
		'sm' => 'SMO ', /* Samoan */
		'sma' => 'SSM ', /* Southern Sami */
		'smj' => 'LSM ', /* Lule Sami */
		'smn' => 'ISM ', /* Inari Sami */
		'sms' => 'SKS ', /* Skolt Sami */
		'snk' => 'SNK ', /* Soninke */
		'so' => 'SML ', /* Somali */
		'sq' => 'SQI ', /* Albanian */
		'sr' => 'SRB ', /* Serbian */
		'srr' => 'SRR ', /* Serer */
		'ss' => 'SWZ ', /* Swazi */
		'st' => 'SOT ', /* [Southern] Sotho */
		'suq' => 'SUR ', /* Suri */
		'sv' => 'SVE ', /* Swedish */
		'sva' => 'SVA ', /* Svan */
		'sw' => 'SWK ', /* Swahili */
		'swb' => 'CMR ', /* Comorian */
		'syr' => 'SYR ', /* Syriac */
		'ta' => 'TAM ', /* Tamil */
		'tab' => 'TAB ', /* Tabasaran */
		'tcy' => 'TUL ', /* Tulu */
		'te' => 'TEL ', /* Telugu */
		'tem' => 'TMN ', /* Temne */
		'tg' => 'TAJ ', /* Tajik */
		'th' => 'THA ', /* Thai */
		'ti' => 'TGY ', /* Tigrinya */
		'tig' => 'TGR ', /* Tigre */
		'tk' => 'TKM ', /* Turkmen */
		'tn' => 'TNA ', /* Tswana */
		'to' => 'TGN ', /* Tonga (Tonga Islands) */
		'tr' => 'TRK ', /* Turkish */
		'tru' => 'TUA ', /* Turoyo Aramaic */
		'ts' => 'TSG ', /* Tsonga */
		'tt' => 'TAT ', /* Tatar */
		'tw' => 'TWI ', /* Twi */
		'ty' => 'THT ', /* Tahitian */
		'tyv' => 'TUV ', /* Tuvin */
		'udm' => 'UDM ', /* Udmurt */
		'ug' => 'UYG ', /* Uighur */
		'uk' => 'UKR ', /* Ukrainian */
		'umb' => 'MBN ', /* [South] Mbundu */
		'unr' => 'MUN ', /* Mundari */
		'ur' => 'URD ', /* Urdu */
		'uz' => 'UZB ', /* Uzbek */
		've' => 'VEN ', /* Venda */
		'vi' => 'VIT ', /* Vietnamese */
		'vmw' => 'MAK ', /* Makua */
		'wbm' => 'WA  ', /* Wa */
		'wbr' => 'WAG ', /* Wagdi */
		'wo' => 'WLF ', /* Wolof */
		'xal' => 'KLM ', /* Kalmyk */
		'xh' => 'XHS ', /* Xhosa */
		'xom' => 'KMO ', /* Komo (Sudan) */
		'xsl' => 'SSL ', /* South Slavey */
		'yi' => 'JII ', /* Yiddish */
		'yid' => 'JII ', /* Yiddish */
		'yo' => 'YBA ', /* Yoruba */
		'yso' => 'NIS ', /* Nisi (China) */
		'zne' => 'ZND ', /* Zande */
		'zu' => 'ZUL ', /* Zulu */
		'zh-cn' => 'ZHS ', /* Chinese (China) */
		'zh-hk' => 'ZHH ', /* Chinese (Hong Kong) */
		'zh-mo' => 'ZHT ', /* Chinese (Macao) */
		'zh-sg' => 'ZHS ', /* Chinese (Singapore) */
		'zh-tw' => 'ZHT ', /* Chinese (Taiwan) */
	];

	// hb-unicode.h
	const UNICODE_GENERAL_CATEGORY_CONTROL = 0;   /* Cc */
	const UNICODE_GENERAL_CATEGORY_FORMAT = 1;   /* Cf */
	const UNICODE_GENERAL_CATEGORY_UNASSIGNED = 2;   /* Cn */
	const UNICODE_GENERAL_CATEGORY_PRIVATE_USE = 3;   /* Co */
	const UNICODE_GENERAL_CATEGORY_SURROGATE = 4;   /* Cs */
	const UNICODE_GENERAL_CATEGORY_LOWERCASE_LETTER = 5;  /* Ll */
	const UNICODE_GENERAL_CATEGORY_MODIFIER_LETTER = 6;  /* Lm */
	const UNICODE_GENERAL_CATEGORY_OTHER_LETTER = 7;  /* Lo */
	const UNICODE_GENERAL_CATEGORY_TITLECASE_LETTER = 8;  /* Lt */
	const UNICODE_GENERAL_CATEGORY_UPPERCASE_LETTER = 9;  /* Lu */
	const UNICODE_GENERAL_CATEGORY_SPACING_MARK = 10;  /* Mc */
	const UNICODE_GENERAL_CATEGORY_ENCLOSING_MARK = 11;  /* Me */
	const UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK = 12;  /* Mn */
	const UNICODE_GENERAL_CATEGORY_DECIMAL_NUMBER = 13;  /* Nd */
	const UNICODE_GENERAL_CATEGORY_LETTER_NUMBER = 14;  /* Nl */
	const UNICODE_GENERAL_CATEGORY_OTHER_NUMBER = 15;  /* No */
	const UNICODE_GENERAL_CATEGORY_CONNECT_PUNCTUATION = 16; /* Pc */
	const UNICODE_GENERAL_CATEGORY_DASH_PUNCTUATION = 17;  /* Pd */
	const UNICODE_GENERAL_CATEGORY_CLOSE_PUNCTUATION = 18; /* Pe */
	const UNICODE_GENERAL_CATEGORY_FINAL_PUNCTUATION = 19; /* Pf */
	const UNICODE_GENERAL_CATEGORY_INITIAL_PUNCTUATION = 20; /* Pi */
	const UNICODE_GENERAL_CATEGORY_OTHER_PUNCTUATION = 21; /* Po */
	const UNICODE_GENERAL_CATEGORY_OPEN_PUNCTUATION = 22;  /* Ps */
	const UNICODE_GENERAL_CATEGORY_CURRENCY_SYMBOL = 23;  /* Sc */
	const UNICODE_GENERAL_CATEGORY_MODIFIER_SYMBOL = 24;  /* Sk */
	const UNICODE_GENERAL_CATEGORY_MATH_SYMBOL = 25;  /* Sm */
	const UNICODE_GENERAL_CATEGORY_OTHER_SYMBOL = 26;  /* So */
	const UNICODE_GENERAL_CATEGORY_LINE_SEPARATOR = 27;  /* Zl */
	const UNICODE_GENERAL_CATEGORY_PARAGRAPH_SEPARATOR = 28; /* Zp */
	const UNICODE_GENERAL_CATEGORY_SPACE_SEPARATOR = 29;  /* Zs */

	function general_category_is_mark($gen_cat)
	{
		return $gen_cat == self::UNICODE_GENERAL_CATEGORY_SPACING_MARK || $gen_cat == self::UNICODE_GENERAL_CATEGORY_ENCLOSING_MARK ||
			$gen_cat == self::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK;
		// define UNICODE_GENERAL_CATEGORY_IS_MARK(gen_cat)
		//if (FLAG(gen_cat) & (FLAG(UNICODE_GENERAL_CATEGORY_SPACING_MARK) | FLAG(UNICODE_GENERAL_CATEGORY_ENCLOSING_MARK) | FLAG(UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK))) { return true; }
	}

	const BIDI_CLASS_L = 0;
	const BIDI_CLASS_LRE = 1;
	const BIDI_CLASS_LRO = 2;
	const BIDI_CLASS_R = 3;
	const BIDI_CLASS_AL = 4;
	const BIDI_CLASS_RLE = 5;
	const BIDI_CLASS_RLO = 6;
	const BIDI_CLASS_PDF = 7;
	const BIDI_CLASS_EN = 8;
	const BIDI_CLASS_ES = 9;
	const BIDI_CLASS_ET = 10;
	const BIDI_CLASS_AN = 11;
	const BIDI_CLASS_CS = 12;
	const BIDI_CLASS_NSM = 13;
	const BIDI_CLASS_BN = 14;
	const BIDI_CLASS_B = 15;
	const BIDI_CLASS_S = 16;
	const BIDI_CLASS_WS = 17;
	const BIDI_CLASS_ON = 18;

	// UNIDATA_VERSION 17.0.0
	/* a list of unique database records */
	/* struct {
	  category;
	  combining;
	  bidi_class;
	  mirrored;
	  east_asian_width;
	  normalization_check;
	  script;
	  }
	 */
	private static $ucd_records = [
		[2, 0, 0, 0, 5, 0, 102],
		[0, 0, 14, 0, 5, 0, 0],
		[0, 0, 16, 0, 5, 0, 0],
		[0, 0, 15, 0, 5, 0, 0],
		[0, 0, 17, 0, 5, 0, 0],
		[29, 0, 17, 0, 3, 0, 0],
		[21, 0, 18, 0, 3, 0, 0],
		[21, 0, 10, 0, 3, 0, 0],
		[23, 0, 10, 0, 3, 0, 0],
		[22, 0, 18, 1, 3, 0, 0],
		[18, 0, 18, 1, 3, 0, 0],
		[25, 0, 9, 0, 3, 0, 0],
		[21, 0, 12, 0, 3, 0, 0],
		[17, 0, 9, 0, 3, 0, 0],
		[13, 0, 8, 0, 3, 0, 0],
		[25, 0, 18, 1, 3, 0, 0],
		[25, 0, 18, 0, 3, 0, 0],
		[9, 0, 0, 0, 3, 0, 1],
		[24, 0, 18, 0, 3, 0, 0],
		[16, 0, 18, 0, 3, 0, 0],
		[5, 0, 0, 0, 3, 0, 1],
		[29, 0, 12, 0, 5, 0, 0],
		[21, 0, 18, 0, 4, 0, 0],
		[23, 0, 10, 0, 4, 0, 0],
		[26, 0, 18, 0, 3, 0, 0],
		[24, 0, 18, 0, 4, 0, 0],
		[26, 0, 18, 0, 5, 0, 0],
		[7, 0, 0, 0, 4, 0, 1],
		[20, 0, 18, 1, 5, 0, 0],
		[1, 0, 14, 0, 4, 0, 0],
		[26, 0, 18, 0, 4, 0, 0],
		[26, 0, 10, 0, 4, 0, 0],
		[25, 0, 10, 0, 4, 0, 0],
		[15, 0, 8, 0, 4, 0, 0],
		[5, 0, 0, 0, 5, 0, 0],
		[19, 0, 18, 1, 5, 0, 0],
		[15, 0, 18, 0, 4, 0, 0],
		[9, 0, 0, 0, 5, 0, 1],
		[9, 0, 0, 0, 4, 0, 1],
		[25, 0, 18, 0, 4, 0, 0],
		[5, 0, 0, 0, 4, 0, 1],
		[5, 0, 0, 0, 5, 0, 1],
		[7, 0, 0, 0, 5, 0, 1],
		[8, 0, 0, 0, 5, 0, 1],
		[6, 0, 0, 0, 5, 0, 1],
		[6, 0, 18, 0, 5, 0, 0],
		[6, 0, 0, 0, 5, 0, 0],
		[24, 0, 18, 0, 5, 0, 0],
		[6, 0, 18, 0, 4, 0, 0],
		[6, 0, 0, 0, 4, 0, 0],
		[24, 0, 18, 0, 5, 0, 34],
		[12, 230, 13, 0, 4, 0, 40],
		[12, 232, 13, 0, 4, 0, 40],
		[12, 220, 13, 0, 4, 0, 40],
		[12, 216, 13, 0, 4, 0, 40],
		[12, 202, 13, 0, 4, 0, 40],
		[12, 1, 13, 0, 4, 0, 40],
		[12, 240, 13, 0, 4, 0, 40],
		[12, 0, 13, 0, 4, 0, 40],
		[12, 233, 13, 0, 4, 0, 40],
		[12, 234, 13, 0, 4, 0, 40],
		[9, 0, 0, 0, 5, 0, 2],
		[5, 0, 0, 0, 5, 0, 2],
		[24, 0, 18, 0, 5, 0, 2],
		[6, 0, 0, 0, 5, 0, 2],
		[21, 0, 18, 0, 5, 0, 0],
		[9, 0, 0, 0, 4, 0, 2],
		[5, 0, 0, 0, 4, 0, 2],
		[9, 0, 0, 0, 5, 0, 54],
		[5, 0, 0, 0, 5, 0, 54],
		[25, 0, 18, 0, 5, 0, 2],
		[9, 0, 0, 0, 5, 0, 3],
		[9, 0, 0, 0, 4, 0, 3],
		[5, 0, 0, 0, 4, 0, 3],
		[5, 0, 0, 0, 5, 0, 3],
		[26, 0, 0, 0, 5, 0, 3],
		[12, 230, 13, 0, 5, 0, 3],
		[12, 230, 13, 0, 5, 0, 40],
		[11, 0, 13, 0, 5, 0, 3],
		[9, 0, 0, 0, 5, 0, 4],
		[6, 0, 0, 0, 5, 0, 4],
		[21, 0, 0, 0, 5, 0, 4],
		[5, 0, 0, 0, 5, 0, 4],
		[17, 0, 18, 0, 5, 0, 4],
		[26, 0, 18, 0, 5, 0, 4],
		[23, 0, 10, 0, 5, 0, 4],
		[2, 0, 3, 0, 5, 0, 102],
		[12, 220, 13, 0, 5, 0, 5],
		[12, 230, 13, 0, 5, 0, 5],
		[12, 222, 13, 0, 5, 0, 5],
		[12, 228, 13, 0, 5, 0, 5],
		[12, 10, 13, 0, 5, 0, 5],
		[12, 11, 13, 0, 5, 0, 5],
		[12, 12, 13, 0, 5, 0, 5],
		[12, 13, 13, 0, 5, 0, 5],
		[12, 14, 13, 0, 5, 0, 5],
		[12, 15, 13, 0, 5, 0, 5],
		[12, 16, 13, 0, 5, 0, 5],
		[12, 17, 13, 0, 5, 0, 5],
		[12, 18, 13, 0, 5, 0, 5],
		[12, 19, 13, 0, 5, 0, 5],
		[12, 20, 13, 0, 5, 0, 5],
		[12, 21, 13, 0, 5, 0, 5],
		[12, 22, 13, 0, 5, 0, 5],
		[17, 0, 3, 0, 5, 0, 5],
		[12, 23, 13, 0, 5, 0, 5],
		[21, 0, 3, 0, 5, 0, 5],
		[12, 24, 13, 0, 5, 0, 5],
		[12, 25, 13, 0, 5, 0, 5],
		[7, 0, 3, 0, 5, 0, 5],
		[1, 0, 11, 0, 5, 0, 6],
		[1, 0, 11, 0, 5, 0, 0],
		[25, 0, 18, 0, 5, 0, 6],
		[25, 0, 4, 0, 5, 0, 6],
		[21, 0, 10, 0, 5, 0, 6],
		[23, 0, 4, 0, 5, 0, 6],
		[21, 0, 12, 0, 5, 0, 0],
		[21, 0, 4, 0, 5, 0, 6],
		[26, 0, 18, 0, 5, 0, 6],
		[12, 230, 13, 0, 5, 0, 6],
		[12, 30, 13, 0, 5, 0, 6],
		[12, 31, 13, 0, 5, 0, 6],
		[12, 32, 13, 0, 5, 0, 6],
		[21, 0, 4, 0, 5, 0, 0],
		[1, 0, 4, 0, 5, 0, 6],
		[7, 0, 4, 0, 5, 0, 6],
		[6, 0, 4, 0, 5, 0, 0],
		[12, 27, 13, 0, 5, 0, 40],
		[12, 28, 13, 0, 5, 0, 40],
		[12, 29, 13, 0, 5, 0, 40],
		[12, 30, 13, 0, 5, 0, 40],
		[12, 31, 13, 0, 5, 0, 40],
		[12, 32, 13, 0, 5, 0, 40],
		[12, 33, 13, 0, 5, 0, 40],
		[12, 34, 13, 0, 5, 0, 40],
		[12, 220, 13, 0, 5, 0, 40],
		[12, 220, 13, 0, 5, 0, 6],
		[13, 0, 11, 0, 5, 0, 6],
		[21, 0, 11, 0, 5, 0, 6],
		[12, 35, 13, 0, 5, 0, 40],
		[6, 0, 4, 0, 5, 0, 6],
		[13, 0, 8, 0, 5, 0, 6],
		[26, 0, 4, 0, 5, 0, 6],
		[21, 0, 4, 0, 5, 0, 7],
		[2, 0, 4, 0, 5, 0, 102],
		[1, 0, 4, 0, 5, 0, 7],
		[7, 0, 4, 0, 5, 0, 7],
		[12, 36, 13, 0, 5, 0, 7],
		[12, 230, 13, 0, 5, 0, 7],
		[12, 220, 13, 0, 5, 0, 7],
		[7, 0, 4, 0, 5, 0, 8],
		[12, 0, 13, 0, 5, 0, 8],
		[13, 0, 3, 0, 5, 0, 65],
		[7, 0, 3, 0, 5, 0, 65],
		[12, 230, 13, 0, 5, 0, 65],
		[12, 220, 13, 0, 5, 0, 65],
		[6, 0, 3, 0, 5, 0, 65],
		[26, 0, 18, 0, 5, 0, 65],
		[21, 0, 18, 0, 5, 0, 65],
		[23, 0, 3, 0, 5, 0, 65],
		[7, 0, 3, 0, 5, 0, 81],
		[12, 230, 13, 0, 5, 0, 81],
		[6, 0, 3, 0, 5, 0, 81],
		[21, 0, 3, 0, 5, 0, 81],
		[7, 0, 3, 0, 5, 0, 94],
		[12, 220, 13, 0, 5, 0, 94],
		[21, 0, 3, 0, 5, 0, 94],
		[24, 0, 4, 0, 5, 0, 6],
		[12, 27, 13, 0, 5, 0, 6],
		[12, 28, 13, 0, 5, 0, 6],
		[12, 29, 13, 0, 5, 0, 6],
		[12, 0, 13, 0, 5, 0, 9],
		[10, 0, 0, 0, 5, 0, 9],
		[7, 0, 0, 0, 5, 0, 9],
		[12, 7, 13, 0, 5, 0, 9],
		[12, 9, 13, 0, 5, 0, 9],
		[21, 0, 0, 0, 5, 0, 0],
		[13, 0, 0, 0, 5, 0, 9],
		[21, 0, 0, 0, 5, 0, 9],
		[6, 0, 0, 0, 5, 0, 9],
		[7, 0, 0, 0, 5, 0, 10],
		[12, 0, 13, 0, 5, 0, 10],
		[10, 0, 0, 0, 5, 0, 10],
		[12, 7, 13, 0, 5, 0, 10],
		[12, 9, 13, 0, 5, 0, 10],
		[13, 0, 0, 0, 5, 0, 10],
		[23, 0, 10, 0, 5, 0, 10],
		[15, 0, 0, 0, 5, 0, 10],
		[26, 0, 0, 0, 5, 0, 10],
		[21, 0, 0, 0, 5, 0, 10],
		[12, 230, 13, 0, 5, 0, 10],
		[12, 0, 13, 0, 5, 0, 11],
		[10, 0, 0, 0, 5, 0, 11],
		[7, 0, 0, 0, 5, 0, 11],
		[12, 7, 13, 0, 5, 0, 11],
		[12, 9, 13, 0, 5, 0, 11],
		[13, 0, 0, 0, 5, 0, 11],
		[21, 0, 0, 0, 5, 0, 11],
		[12, 0, 13, 0, 5, 0, 12],
		[10, 0, 0, 0, 5, 0, 12],
		[7, 0, 0, 0, 5, 0, 12],
		[12, 7, 13, 0, 5, 0, 12],
		[12, 9, 13, 0, 5, 0, 12],
		[13, 0, 0, 0, 5, 0, 12],
		[21, 0, 0, 0, 5, 0, 12],
		[23, 0, 10, 0, 5, 0, 12],
		[12, 0, 13, 0, 5, 0, 13],
		[10, 0, 0, 0, 5, 0, 13],
		[7, 0, 0, 0, 5, 0, 13],
		[12, 7, 13, 0, 5, 0, 13],
		[12, 9, 13, 0, 5, 0, 13],
		[13, 0, 0, 0, 5, 0, 13],
		[26, 0, 0, 0, 5, 0, 13],
		[15, 0, 0, 0, 5, 0, 13],
		[12, 0, 13, 0, 5, 0, 14],
		[7, 0, 0, 0, 5, 0, 14],
		[10, 0, 0, 0, 5, 0, 14],
		[12, 9, 13, 0, 5, 0, 14],
		[13, 0, 0, 0, 5, 0, 14],
		[15, 0, 0, 0, 5, 0, 14],
		[26, 0, 18, 0, 5, 0, 14],
		[23, 0, 10, 0, 5, 0, 14],
		[12, 0, 13, 0, 5, 0, 15],
		[10, 0, 0, 0, 5, 0, 15],
		[7, 0, 0, 0, 5, 0, 15],
		[12, 7, 13, 0, 5, 0, 15],
		[12, 9, 13, 0, 5, 0, 15],
		[12, 84, 13, 0, 5, 0, 15],
		[12, 91, 13, 0, 5, 0, 15],
		[13, 0, 0, 0, 5, 0, 15],
		[21, 0, 0, 0, 5, 0, 15],
		[15, 0, 18, 0, 5, 0, 15],
		[26, 0, 0, 0, 5, 0, 15],
		[7, 0, 0, 0, 5, 0, 16],
		[12, 0, 13, 0, 5, 0, 16],
		[10, 0, 0, 0, 5, 0, 16],
		[21, 0, 0, 0, 5, 0, 16],
		[12, 7, 13, 0, 5, 0, 16],
		[12, 0, 0, 0, 5, 0, 16],
		[12, 9, 13, 0, 5, 0, 16],
		[13, 0, 0, 0, 5, 0, 16],
		[12, 0, 13, 0, 5, 0, 17],
		[10, 0, 0, 0, 5, 0, 17],
		[7, 0, 0, 0, 5, 0, 17],
		[12, 9, 13, 0, 5, 0, 17],
		[26, 0, 0, 0, 5, 0, 17],
		[15, 0, 0, 0, 5, 0, 17],
		[13, 0, 0, 0, 5, 0, 17],
		[12, 0, 13, 0, 5, 0, 18],
		[10, 0, 0, 0, 5, 0, 18],
		[7, 0, 0, 0, 5, 0, 18],
		[12, 9, 13, 0, 5, 0, 18],
		[13, 0, 0, 0, 5, 0, 18],
		[21, 0, 0, 0, 5, 0, 18],
		[7, 0, 0, 0, 5, 0, 19],
		[12, 0, 13, 0, 5, 0, 19],
		[12, 103, 13, 0, 5, 0, 19],
		[12, 9, 13, 0, 5, 0, 19],
		[23, 0, 10, 0, 5, 0, 0],
		[6, 0, 0, 0, 5, 0, 19],
		[12, 107, 13, 0, 5, 0, 19],
		[21, 0, 0, 0, 5, 0, 19],
		[13, 0, 0, 0, 5, 0, 19],
		[7, 0, 0, 0, 5, 0, 20],
		[12, 0, 13, 0, 5, 0, 20],
		[12, 118, 13, 0, 5, 0, 20],
		[12, 9, 13, 0, 5, 0, 20],
		[6, 0, 0, 0, 5, 0, 20],
		[12, 122, 13, 0, 5, 0, 20],
		[13, 0, 0, 0, 5, 0, 20],
		[7, 0, 0, 0, 5, 0, 21],
		[26, 0, 0, 0, 5, 0, 21],
		[21, 0, 0, 0, 5, 0, 21],
		[12, 220, 13, 0, 5, 0, 21],
		[13, 0, 0, 0, 5, 0, 21],
		[15, 0, 0, 0, 5, 0, 21],
		[12, 216, 13, 0, 5, 0, 21],
		[22, 0, 18, 1, 5, 0, 21],
		[18, 0, 18, 1, 5, 0, 21],
		[10, 0, 0, 0, 5, 0, 21],
		[12, 129, 13, 0, 5, 0, 21],
		[12, 130, 13, 0, 5, 0, 21],
		[12, 0, 13, 0, 5, 0, 21],
		[12, 132, 13, 0, 5, 0, 21],
		[12, 230, 13, 0, 5, 0, 21],
		[12, 9, 13, 0, 5, 0, 21],
		[26, 0, 0, 0, 5, 0, 0],
		[7, 0, 0, 0, 5, 0, 22],
		[10, 0, 0, 0, 5, 0, 22],
		[12, 0, 13, 0, 5, 0, 22],
		[12, 7, 13, 0, 5, 0, 22],
		[12, 9, 13, 0, 5, 0, 22],
		[13, 0, 0, 0, 5, 0, 22],
		[21, 0, 0, 0, 5, 0, 22],
		[12, 220, 13, 0, 5, 0, 22],
		[26, 0, 0, 0, 5, 0, 22],
		[9, 0, 0, 0, 5, 0, 23],
		[5, 0, 0, 0, 5, 0, 23],
		[6, 0, 0, 0, 5, 0, 23],
		[7, 0, 0, 0, 2, 0, 24],
		[7, 0, 0, 0, 5, 0, 24],
		[7, 0, 0, 0, 5, 0, 25],
		[12, 230, 13, 0, 5, 0, 25],
		[21, 0, 0, 0, 5, 0, 25],
		[15, 0, 0, 0, 5, 0, 25],
		[26, 0, 18, 0, 5, 0, 25],
		[9, 0, 0, 0, 5, 0, 26],
		[5, 0, 0, 0, 5, 0, 26],
		[17, 0, 18, 0, 5, 0, 27],
		[7, 0, 0, 0, 5, 0, 27],
		[26, 0, 0, 0, 5, 0, 27],
		[21, 0, 0, 0, 5, 0, 27],
		[29, 0, 17, 0, 5, 0, 28],
		[7, 0, 0, 0, 5, 0, 28],
		[22, 0, 18, 1, 5, 0, 28],
		[18, 0, 18, 1, 5, 0, 28],
		[7, 0, 0, 0, 5, 0, 29],
		[14, 0, 0, 0, 5, 0, 29],
		[7, 0, 0, 0, 5, 0, 41],
		[12, 0, 13, 0, 5, 0, 41],
		[12, 9, 13, 0, 5, 0, 41],
		[10, 9, 0, 0, 5, 0, 41],
		[7, 0, 0, 0, 5, 0, 42],
		[12, 0, 13, 0, 5, 0, 42],
		[10, 9, 0, 0, 5, 0, 42],
		[7, 0, 0, 0, 5, 0, 43],
		[12, 0, 13, 0, 5, 0, 43],
		[7, 0, 0, 0, 5, 0, 44],
		[12, 0, 13, 0, 5, 0, 44],
		[7, 0, 0, 0, 5, 0, 30],
		[12, 0, 13, 0, 5, 0, 30],
		[10, 0, 0, 0, 5, 0, 30],
		[12, 9, 13, 0, 5, 0, 30],
		[21, 0, 0, 0, 5, 0, 30],
		[6, 0, 0, 0, 5, 0, 30],
		[23, 0, 10, 0, 5, 0, 30],
		[12, 230, 13, 0, 5, 0, 30],
		[13, 0, 0, 0, 5, 0, 30],
		[15, 0, 18, 0, 5, 0, 30],
		[21, 0, 18, 0, 5, 0, 31],
		[17, 0, 18, 0, 5, 0, 31],
		[12, 0, 13, 0, 5, 0, 31],
		[1, 0, 14, 0, 5, 0, 31],
		[13, 0, 0, 0, 5, 0, 31],
		[7, 0, 0, 0, 5, 0, 31],
		[6, 0, 0, 0, 5, 0, 31],
		[12, 228, 13, 0, 5, 0, 31],
		[7, 0, 0, 0, 5, 0, 45],
		[12, 0, 13, 0, 5, 0, 45],
		[10, 0, 0, 0, 5, 0, 45],
		[12, 222, 13, 0, 5, 0, 45],
		[12, 230, 13, 0, 5, 0, 45],
		[12, 220, 13, 0, 5, 0, 45],
		[26, 0, 18, 0, 5, 0, 45],
		[21, 0, 18, 0, 5, 0, 45],
		[13, 0, 0, 0, 5, 0, 45],
		[7, 0, 0, 0, 5, 0, 46],
		[7, 0, 0, 0, 5, 0, 55],
		[13, 0, 0, 0, 5, 0, 55],
		[15, 0, 0, 0, 5, 0, 55],
		[26, 0, 18, 0, 5, 0, 55],
		[26, 0, 18, 0, 5, 0, 30],
		[7, 0, 0, 0, 5, 0, 53],
		[12, 230, 13, 0, 5, 0, 53],
		[12, 220, 13, 0, 5, 0, 53],
		[10, 0, 0, 0, 5, 0, 53],
		[12, 0, 13, 0, 5, 0, 53],
		[21, 0, 0, 0, 5, 0, 53],
		[7, 0, 0, 0, 5, 0, 77],
		[10, 0, 0, 0, 5, 0, 77],
		[12, 0, 13, 0, 5, 0, 77],
		[12, 9, 13, 0, 5, 0, 77],
		[12, 230, 13, 0, 5, 0, 77],
		[12, 220, 13, 0, 5, 0, 77],
		[13, 0, 0, 0, 5, 0, 77],
		[21, 0, 0, 0, 5, 0, 77],
		[6, 0, 0, 0, 5, 0, 77],
		[11, 0, 13, 0, 5, 0, 40],
		[12, 234, 13, 0, 5, 0, 40],
		[12, 0, 13, 0, 5, 0, 61],
		[10, 0, 0, 0, 5, 0, 61],
		[7, 0, 0, 0, 5, 0, 61],
		[12, 7, 13, 0, 5, 0, 61],
		[10, 9, 0, 0, 5, 0, 61],
		[21, 0, 0, 0, 5, 0, 61],
		[13, 0, 0, 0, 5, 0, 61],
		[26, 0, 0, 0, 5, 0, 61],
		[12, 230, 13, 0, 5, 0, 61],
		[12, 220, 13, 0, 5, 0, 61],
		[12, 0, 13, 0, 5, 0, 66],
		[10, 0, 0, 0, 5, 0, 66],
		[7, 0, 0, 0, 5, 0, 66],
		[10, 9, 0, 0, 5, 0, 66],
		[12, 9, 13, 0, 5, 0, 66],
		[13, 0, 0, 0, 5, 0, 66],
		[7, 0, 0, 0, 5, 0, 92],
		[12, 7, 13, 0, 5, 0, 92],
		[10, 0, 0, 0, 5, 0, 92],
		[12, 0, 13, 0, 5, 0, 92],
		[10, 9, 0, 0, 5, 0, 92],
		[21, 0, 0, 0, 5, 0, 92],
		[7, 0, 0, 0, 5, 0, 67],
		[10, 0, 0, 0, 5, 0, 67],
		[12, 0, 13, 0, 5, 0, 67],
		[12, 7, 13, 0, 5, 0, 67],
		[21, 0, 0, 0, 5, 0, 67],
		[13, 0, 0, 0, 5, 0, 67],
		[13, 0, 0, 0, 5, 0, 68],
		[7, 0, 0, 0, 5, 0, 68],
		[6, 0, 0, 0, 5, 0, 68],
		[21, 0, 0, 0, 5, 0, 68],
		[21, 0, 0, 0, 5, 0, 66],
		[12, 1, 13, 0, 5, 0, 40],
		[10, 0, 0, 0, 5, 0, 0],
		[7, 0, 0, 0, 5, 0, 0],
		[6, 0, 0, 0, 5, 0, 3],
		[12, 214, 13, 0, 5, 0, 40],
		[12, 202, 13, 0, 5, 0, 40],
		[12, 232, 13, 0, 5, 0, 40],
		[12, 228, 13, 0, 5, 0, 40],
		[12, 218, 13, 0, 5, 0, 40],
		[12, 233, 13, 0, 5, 0, 40],
		[8, 0, 0, 0, 5, 0, 2],
		[29, 0, 17, 0, 5, 0, 0],
		[1, 0, 14, 0, 5, 0, 0],
		[1, 0, 14, 0, 5, 0, 40],
		[1, 0, 0, 0, 5, 0, 0],
		[1, 0, 3, 0, 5, 0, 0],
		[17, 0, 18, 0, 4, 0, 0],
		[17, 0, 18, 0, 5, 0, 0],
		[20, 0, 18, 0, 4, 0, 0],
		[19, 0, 18, 0, 4, 0, 0],
		[22, 0, 18, 0, 5, 0, 0],
		[20, 0, 18, 0, 5, 0, 0],
		[27, 0, 17, 0, 5, 0, 0],
		[28, 0, 15, 0, 5, 0, 0],
		[1, 0, 1, 0, 5, 0, 0],
		[1, 0, 5, 0, 5, 0, 0],
		[1, 0, 7, 0, 5, 0, 0],
		[1, 0, 2, 0, 5, 0, 0],
		[1, 0, 6, 0, 5, 0, 0],
		[21, 0, 10, 0, 4, 0, 0],
		[21, 0, 10, 0, 5, 0, 0],
		[16, 0, 18, 0, 5, 0, 0],
		[25, 0, 12, 0, 5, 0, 0],
		[22, 0, 18, 1, 5, 0, 0],
		[18, 0, 18, 1, 5, 0, 0],
		[25, 0, 18, 0, 5, 0, 0],
		[2, 0, 14, 0, 5, 0, 102],
		[1, 0, 18, 0, 5, 0, 0],
		[15, 0, 8, 0, 5, 0, 0],
		[25, 0, 9, 0, 5, 0, 0],
		[6, 0, 0, 0, 4, 0, 1],
		[23, 0, 10, 0, 1, 0, 0],
		[2, 0, 10, 0, 5, 0, 102],
		[9, 0, 0, 0, 5, 0, 0],
		[5, 0, 0, 0, 4, 0, 0],
		[26, 0, 10, 0, 5, 0, 0],
		[25, 0, 18, 1, 5, 0, 0],
		[15, 0, 18, 0, 5, 0, 0],
		[14, 0, 0, 0, 4, 0, 1],
		[14, 0, 0, 0, 5, 0, 1],
		[25, 0, 18, 1, 4, 0, 0],
		[25, 0, 10, 0, 5, 0, 0],
		[26, 0, 18, 0, 2, 0, 0],
		[22, 0, 18, 1, 2, 0, 0],
		[18, 0, 18, 1, 2, 0, 0],
		[26, 0, 0, 0, 4, 0, 0],
		[25, 0, 18, 0, 2, 0, 0],
		[26, 0, 0, 0, 5, 0, 52],
		[26, 0, 18, 1, 5, 0, 0],
		[9, 0, 0, 0, 5, 0, 56],
		[5, 0, 0, 0, 5, 0, 56],
		[26, 0, 18, 0, 5, 0, 54],
		[12, 230, 13, 0, 5, 0, 54],
		[21, 0, 18, 0, 5, 0, 54],
		[15, 0, 18, 0, 5, 0, 54],
		[7, 0, 0, 0, 5, 0, 57],
		[6, 0, 0, 0, 5, 0, 57],
		[21, 0, 0, 0, 5, 0, 57],
		[12, 9, 13, 0, 5, 0, 57],
		[26, 0, 18, 0, 2, 0, 35],
		[29, 0, 17, 0, 0, 0, 0],
		[21, 0, 18, 0, 2, 0, 0],
		[6, 0, 0, 0, 2, 0, 35],
		[7, 0, 0, 0, 2, 0, 0],
		[14, 0, 0, 0, 2, 0, 35],
		[17, 0, 18, 0, 2, 0, 0],
		[22, 0, 18, 0, 2, 0, 0],
		[18, 0, 18, 0, 2, 0, 0],
		[12, 218, 13, 0, 2, 0, 40],
		[12, 228, 13, 0, 2, 0, 40],
		[12, 232, 13, 0, 2, 0, 40],
		[12, 222, 13, 0, 2, 0, 40],
		[10, 224, 0, 0, 2, 0, 24],
		[6, 0, 0, 0, 2, 0, 0],
		[7, 0, 0, 0, 2, 0, 32],
		[12, 8, 13, 0, 2, 0, 40],
		[24, 0, 18, 0, 2, 0, 0],
		[6, 0, 0, 0, 2, 0, 32],
		[7, 0, 0, 0, 2, 0, 33],
		[6, 0, 0, 0, 2, 0, 33],
		[7, 0, 0, 0, 2, 0, 34],
		[26, 0, 0, 0, 2, 0, 0],
		[15, 0, 0, 0, 2, 0, 0],
		[26, 0, 0, 0, 2, 0, 24],
		[26, 0, 18, 0, 2, 0, 24],
		[15, 0, 0, 0, 4, 0, 0],
		[15, 0, 18, 0, 2, 0, 0],
		[26, 0, 0, 0, 2, 0, 33],
		[7, 0, 0, 0, 2, 0, 35],
		[7, 0, 0, 0, 2, 0, 36],
		[6, 0, 0, 0, 2, 0, 36],
		[26, 0, 18, 0, 2, 0, 36],
		[7, 0, 0, 0, 5, 0, 82],
		[6, 0, 0, 0, 5, 0, 82],
		[21, 0, 0, 0, 5, 0, 82],
		[7, 0, 0, 0, 5, 0, 69],
		[6, 0, 0, 0, 5, 0, 69],
		[21, 0, 18, 0, 5, 0, 69],
		[13, 0, 0, 0, 5, 0, 69],
		[7, 0, 0, 0, 5, 0, 3],
		[21, 0, 18, 0, 5, 0, 3],
		[6, 0, 18, 0, 5, 0, 3],
		[7, 0, 0, 0, 5, 0, 83],
		[14, 0, 0, 0, 5, 0, 83],
		[12, 230, 13, 0, 5, 0, 83],
		[21, 0, 0, 0, 5, 0, 83],
		[24, 0, 0, 0, 5, 0, 0],
		[7, 0, 0, 0, 5, 0, 58],
		[12, 0, 13, 0, 5, 0, 58],
		[12, 9, 13, 0, 5, 0, 58],
		[10, 0, 0, 0, 5, 0, 58],
		[26, 0, 18, 0, 5, 0, 58],
		[15, 0, 0, 0, 5, 0, 0],
		[7, 0, 0, 0, 5, 0, 64],
		[21, 0, 18, 0, 5, 0, 64],
		[10, 0, 0, 0, 5, 0, 70],
		[7, 0, 0, 0, 5, 0, 70],
		[12, 9, 13, 0, 5, 0, 70],
		[12, 0, 13, 0, 5, 0, 70],
		[21, 0, 0, 0, 5, 0, 70],
		[13, 0, 0, 0, 5, 0, 70],
		[12, 230, 13, 0, 5, 0, 9],
		[13, 0, 0, 0, 5, 0, 71],
		[7, 0, 0, 0, 5, 0, 71],
		[12, 0, 13, 0, 5, 0, 71],
		[12, 220, 13, 0, 5, 0, 71],
		[21, 0, 0, 0, 5, 0, 71],
		[7, 0, 0, 0, 5, 0, 72],
		[12, 0, 13, 0, 5, 0, 72],
		[10, 0, 0, 0, 5, 0, 72],
		[10, 9, 0, 0, 5, 0, 72],
		[21, 0, 0, 0, 5, 0, 72],
		[12, 0, 13, 0, 5, 0, 84],
		[10, 0, 0, 0, 5, 0, 84],
		[7, 0, 0, 0, 5, 0, 84],
		[12, 7, 13, 0, 5, 0, 84],
		[10, 9, 0, 0, 5, 0, 84],
		[21, 0, 0, 0, 5, 0, 84],
		[13, 0, 0, 0, 5, 0, 84],
		[6, 0, 0, 0, 5, 0, 22],
		[7, 0, 0, 0, 5, 0, 76],
		[12, 0, 13, 0, 5, 0, 76],
		[10, 0, 0, 0, 5, 0, 76],
		[13, 0, 0, 0, 5, 0, 76],
		[21, 0, 0, 0, 5, 0, 76],
		[7, 0, 0, 0, 5, 0, 78],
		[12, 230, 13, 0, 5, 0, 78],
		[12, 220, 13, 0, 5, 0, 78],
		[6, 0, 0, 0, 5, 0, 78],
		[21, 0, 0, 0, 5, 0, 78],
		[7, 0, 0, 0, 5, 0, 85],
		[10, 0, 0, 0, 5, 0, 85],
		[12, 0, 13, 0, 5, 0, 85],
		[21, 0, 0, 0, 5, 0, 85],
		[6, 0, 0, 0, 5, 0, 85],
		[12, 9, 13, 0, 5, 0, 85],
		[13, 0, 0, 0, 5, 0, 85],
		[4, 0, 0, 0, 5, 0, 102],
		[3, 0, 0, 0, 4, 0, 102],
		[2, 0, 0, 0, 2, 0, 102],
		[12, 26, 13, 0, 5, 0, 5],
		[25, 0, 9, 0, 5, 0, 5],
		[18, 0, 18, 0, 5, 0, 0],
		[16, 0, 18, 0, 2, 0, 0],
		[21, 0, 12, 0, 2, 0, 0],
		[21, 0, 10, 0, 2, 0, 0],
		[25, 0, 9, 0, 2, 0, 0],
		[17, 0, 9, 0, 2, 0, 0],
		[25, 0, 18, 1, 2, 0, 0],
		[23, 0, 10, 0, 2, 0, 0],
		[21, 0, 18, 0, 0, 0, 0],
		[21, 0, 10, 0, 0, 0, 0],
		[23, 0, 10, 0, 0, 0, 0],
		[22, 0, 18, 1, 0, 0, 0],
		[18, 0, 18, 1, 0, 0, 0],
		[25, 0, 9, 0, 0, 0, 0],
		[21, 0, 12, 0, 0, 0, 0],
		[17, 0, 9, 0, 0, 0, 0],
		[13, 0, 8, 0, 0, 0, 0],
		[25, 0, 18, 1, 0, 0, 0],
		[25, 0, 18, 0, 0, 0, 0],
		[9, 0, 0, 0, 0, 0, 1],
		[24, 0, 18, 0, 0, 0, 0],
		[16, 0, 18, 0, 0, 0, 0],
		[5, 0, 0, 0, 0, 0, 1],
		[21, 0, 18, 0, 1, 0, 0],
		[22, 0, 18, 1, 1, 0, 0],
		[18, 0, 18, 1, 1, 0, 0],
		[7, 0, 0, 0, 1, 0, 33],
		[6, 0, 0, 0, 1, 0, 0],
		[7, 0, 0, 0, 1, 0, 24],
		[26, 0, 18, 0, 0, 0, 0],
		[26, 0, 18, 0, 1, 0, 0],
		[25, 0, 18, 0, 1, 0, 0],
		[7, 0, 0, 0, 5, 0, 47],
		[14, 0, 18, 0, 5, 0, 2],
		[15, 0, 18, 0, 5, 0, 2],
		[26, 0, 18, 0, 5, 0, 2],
		[26, 0, 0, 0, 5, 0, 2],
		[7, 0, 0, 0, 5, 0, 73],
		[7, 0, 0, 0, 5, 0, 74],
		[7, 0, 0, 0, 5, 0, 37],
		[15, 0, 0, 0, 5, 0, 37],
		[7, 0, 0, 0, 5, 0, 38],
		[14, 0, 0, 0, 5, 0, 38],
		[7, 0, 0, 0, 5, 0, 148],
		[12, 230, 13, 0, 5, 0, 148],
		[7, 0, 0, 0, 5, 0, 48],
		[21, 0, 0, 0, 5, 0, 48],
		[7, 0, 0, 0, 5, 0, 59],
		[21, 0, 0, 0, 5, 0, 59],
		[14, 0, 0, 0, 5, 0, 59],
		[9, 0, 0, 0, 5, 0, 39],
		[5, 0, 0, 0, 5, 0, 39],
		[7, 0, 0, 0, 5, 0, 49],
		[7, 0, 0, 0, 5, 0, 50],
		[13, 0, 0, 0, 5, 0, 50],
		[9, 0, 0, 0, 5, 0, 151],
		[5, 0, 0, 0, 5, 0, 151],
		[7, 0, 0, 0, 5, 0, 115],
		[7, 0, 0, 0, 5, 0, 109],
		[21, 0, 0, 0, 5, 0, 109],
		[9, 0, 0, 0, 5, 0, 170],
		[5, 0, 0, 0, 5, 0, 170],
		[7, 0, 0, 0, 5, 0, 166],
		[7, 0, 0, 0, 5, 0, 128],
		[7, 0, 3, 0, 5, 0, 51],
		[7, 0, 3, 0, 5, 0, 86],
		[21, 0, 3, 0, 5, 0, 86],
		[15, 0, 3, 0, 5, 0, 86],
		[7, 0, 3, 0, 5, 0, 153],
		[26, 0, 3, 0, 5, 0, 153],
		[15, 0, 3, 0, 5, 0, 153],
		[7, 0, 3, 0, 5, 0, 139],
		[15, 0, 3, 0, 5, 0, 139],
		[7, 0, 3, 0, 5, 0, 122],
		[15, 0, 3, 0, 5, 0, 122],
		[7, 0, 3, 0, 5, 0, 63],
		[15, 0, 3, 0, 5, 0, 63],
		[21, 0, 18, 0, 5, 0, 63],
		[7, 0, 3, 0, 5, 0, 75],
		[21, 0, 3, 0, 5, 0, 75],
		[7, 0, 3, 0, 5, 0, 157],
		[7, 0, 3, 0, 5, 0, 97],
		[7, 0, 3, 0, 5, 0, 96],
		[15, 0, 3, 0, 5, 0, 96],
		[7, 0, 3, 0, 5, 0, 60],
		[12, 0, 13, 0, 5, 0, 60],
		[12, 220, 13, 0, 5, 0, 60],
		[12, 230, 13, 0, 5, 0, 60],
		[12, 1, 13, 0, 5, 0, 60],
		[12, 9, 13, 0, 5, 0, 60],
		[15, 0, 3, 0, 5, 0, 60],
		[21, 0, 3, 0, 5, 0, 60],
		[7, 0, 3, 0, 5, 0, 87],
		[15, 0, 3, 0, 5, 0, 87],
		[21, 0, 3, 0, 5, 0, 87],
		[7, 0, 3, 0, 5, 0, 147],
		[15, 0, 3, 0, 5, 0, 147],
		[7, 0, 3, 0, 5, 0, 131],
		[26, 0, 3, 0, 5, 0, 131],
		[12, 230, 13, 0, 5, 0, 131],
		[12, 220, 13, 0, 5, 0, 131],
		[15, 0, 3, 0, 5, 0, 131],
		[21, 0, 3, 0, 5, 0, 131],
		[7, 0, 3, 0, 5, 0, 79],
		[21, 0, 18, 0, 5, 0, 79],
		[7, 0, 3, 0, 5, 0, 88],
		[15, 0, 3, 0, 5, 0, 88],
		[7, 0, 3, 0, 5, 0, 89],
		[15, 0, 3, 0, 5, 0, 89],
		[7, 0, 3, 0, 5, 0, 155],
		[21, 0, 3, 0, 5, 0, 155],
		[15, 0, 3, 0, 5, 0, 155],
		[7, 0, 3, 0, 5, 0, 90],
		[9, 0, 3, 0, 5, 0, 146],
		[5, 0, 3, 0, 5, 0, 146],
		[15, 0, 3, 0, 5, 0, 146],
		[7, 0, 4, 0, 5, 0, 121],
		[12, 230, 13, 0, 5, 0, 121],
		[13, 0, 11, 0, 5, 0, 121],
		[13, 0, 11, 0, 5, 0, 117],
		[7, 0, 3, 0, 5, 0, 117],
		[6, 0, 3, 0, 5, 0, 117],
		[9, 0, 3, 0, 5, 0, 117],
		[12, 230, 13, 0, 5, 0, 117],
		[17, 0, 18, 0, 5, 0, 117],
		[5, 0, 3, 0, 5, 0, 117],
		[25, 0, 3, 0, 5, 0, 117],
		[15, 0, 11, 0, 5, 0, 6],
		[7, 0, 3, 0, 5, 0, 173],
		[12, 230, 13, 0, 5, 0, 173],
		[17, 0, 3, 0, 5, 0, 173],
		[21, 0, 18, 0, 5, 0, 6],
		[12, 0, 13, 0, 5, 0, 6],
		[7, 0, 3, 0, 5, 0, 149],
		[15, 0, 3, 0, 5, 0, 149],
		[7, 0, 4, 0, 5, 0, 159],
		[12, 220, 13, 0, 5, 0, 159],
		[12, 230, 13, 0, 5, 0, 159],
		[15, 0, 4, 0, 5, 0, 159],
		[21, 0, 4, 0, 5, 0, 159],
		[7, 0, 3, 0, 5, 0, 150],
		[12, 230, 13, 0, 5, 0, 150],
		[12, 220, 13, 0, 5, 0, 150],
		[21, 0, 3, 0, 5, 0, 150],
		[7, 0, 3, 0, 5, 0, 110],
		[15, 0, 3, 0, 5, 0, 110],
		[7, 0, 3, 0, 5, 0, 116],
		[10, 0, 0, 0, 5, 0, 93],
		[12, 0, 13, 0, 5, 0, 93],
		[7, 0, 0, 0, 5, 0, 93],
		[12, 9, 13, 0, 5, 0, 93],
		[21, 0, 0, 0, 5, 0, 93],
		[15, 0, 18, 0, 5, 0, 93],
		[13, 0, 0, 0, 5, 0, 93],
		[12, 0, 13, 0, 5, 0, 91],
		[10, 0, 0, 0, 5, 0, 91],
		[7, 0, 0, 0, 5, 0, 91],
		[12, 9, 13, 0, 5, 0, 91],
		[12, 7, 13, 0, 5, 0, 91],
		[21, 0, 0, 0, 5, 0, 91],
		[1, 0, 0, 0, 5, 0, 91],
		[7, 0, 0, 0, 5, 0, 100],
		[13, 0, 0, 0, 5, 0, 100],
		[12, 230, 13, 0, 5, 0, 95],
		[7, 0, 0, 0, 5, 0, 95],
		[12, 0, 13, 0, 5, 0, 95],
		[10, 0, 0, 0, 5, 0, 95],
		[12, 9, 13, 0, 5, 0, 95],
		[13, 0, 0, 0, 5, 0, 95],
		[21, 0, 0, 0, 5, 0, 95],
		[7, 0, 0, 0, 5, 0, 129],
		[12, 7, 13, 0, 5, 0, 129],
		[21, 0, 0, 0, 5, 0, 129],
		[12, 0, 13, 0, 5, 0, 99],
		[10, 0, 0, 0, 5, 0, 99],
		[7, 0, 0, 0, 5, 0, 99],
		[10, 9, 0, 0, 5, 0, 99],
		[21, 0, 0, 0, 5, 0, 99],
		[12, 7, 13, 0, 5, 0, 99],
		[13, 0, 0, 0, 5, 0, 99],
		[15, 0, 0, 0, 5, 0, 18],
		[7, 0, 0, 0, 5, 0, 125],
		[10, 0, 0, 0, 5, 0, 125],
		[12, 0, 13, 0, 5, 0, 125],
		[10, 9, 0, 0, 5, 0, 125],
		[12, 7, 13, 0, 5, 0, 125],
		[21, 0, 0, 0, 5, 0, 125],
		[7, 0, 0, 0, 5, 0, 138],
		[21, 0, 0, 0, 5, 0, 138],
		[7, 0, 0, 0, 5, 0, 126],
		[12, 0, 13, 0, 5, 0, 126],
		[10, 0, 0, 0, 5, 0, 126],
		[12, 7, 13, 0, 5, 0, 126],
		[12, 9, 13, 0, 5, 0, 126],
		[13, 0, 0, 0, 5, 0, 126],
		[12, 0, 13, 0, 5, 0, 118],
		[10, 0, 0, 0, 5, 0, 118],
		[7, 0, 0, 0, 5, 0, 118],
		[12, 7, 13, 0, 5, 0, 40],
		[12, 7, 13, 0, 5, 0, 118],
		[10, 9, 0, 0, 5, 0, 118],
		[12, 230, 13, 0, 5, 0, 118],
		[7, 0, 0, 0, 5, 0, 169],
		[10, 0, 0, 0, 5, 0, 169],
		[12, 0, 13, 0, 5, 0, 169],
		[12, 9, 13, 0, 5, 0, 169],
		[10, 9, 0, 0, 5, 0, 169],
		[21, 0, 0, 0, 5, 0, 169],
		[7, 0, 0, 0, 5, 0, 142],
		[10, 0, 0, 0, 5, 0, 142],
		[12, 0, 13, 0, 5, 0, 142],
		[12, 9, 13, 0, 5, 0, 142],
		[12, 7, 13, 0, 5, 0, 142],
		[21, 0, 0, 0, 5, 0, 142],
		[13, 0, 0, 0, 5, 0, 142],
		[12, 230, 13, 0, 5, 0, 142],
		[7, 0, 0, 0, 5, 0, 165],
		[10, 0, 0, 0, 5, 0, 165],
		[12, 0, 13, 0, 5, 0, 165],
		[12, 9, 13, 0, 5, 0, 165],
		[12, 7, 13, 0, 5, 0, 165],
		[21, 0, 0, 0, 5, 0, 165],
		[13, 0, 0, 0, 5, 0, 165],
		[7, 0, 0, 0, 5, 0, 156],
		[10, 0, 0, 0, 5, 0, 156],
		[12, 0, 13, 0, 5, 0, 156],
		[12, 9, 13, 0, 5, 0, 156],
		[12, 7, 13, 0, 5, 0, 156],
		[21, 0, 0, 0, 5, 0, 156],
		[7, 0, 0, 0, 5, 0, 136],
		[10, 0, 0, 0, 5, 0, 136],
		[12, 0, 13, 0, 5, 0, 136],
		[12, 9, 13, 0, 5, 0, 136],
		[21, 0, 0, 0, 5, 0, 136],
		[13, 0, 0, 0, 5, 0, 136],
		[7, 0, 0, 0, 5, 0, 101],
		[12, 0, 13, 0, 5, 0, 101],
		[10, 0, 0, 0, 5, 0, 101],
		[10, 9, 0, 0, 5, 0, 101],
		[12, 7, 13, 0, 5, 0, 101],
		[21, 0, 0, 0, 5, 0, 101],
		[13, 0, 0, 0, 5, 0, 101],
		[7, 0, 0, 0, 5, 0, 104],
		[12, 0, 13, 0, 5, 0, 104],
		[10, 0, 0, 0, 5, 0, 104],
		[12, 9, 13, 0, 5, 0, 104],
		[13, 0, 0, 0, 5, 0, 104],
		[15, 0, 0, 0, 5, 0, 104],
		[21, 0, 0, 0, 5, 0, 104],
		[26, 0, 0, 0, 5, 0, 104],
		[7, 0, 0, 0, 5, 0, 113],
		[10, 0, 0, 0, 5, 0, 113],
		[12, 0, 13, 0, 5, 0, 113],
		[12, 9, 13, 0, 5, 0, 113],
		[12, 7, 13, 0, 5, 0, 113],
		[21, 0, 0, 0, 5, 0, 113],
		[9, 0, 0, 0, 5, 0, 172],
		[5, 0, 0, 0, 5, 0, 172],
		[13, 0, 0, 0, 5, 0, 172],
		[15, 0, 0, 0, 5, 0, 172],
		[7, 0, 0, 0, 5, 0, 172],
		[7, 0, 0, 0, 5, 0, 112],
		[10, 0, 0, 0, 5, 0, 112],
		[12, 0, 13, 0, 5, 0, 112],
		[10, 9, 0, 0, 5, 0, 112],
		[12, 9, 13, 0, 5, 0, 112],
		[12, 7, 13, 0, 5, 0, 112],
		[21, 0, 0, 0, 5, 0, 112],
		[13, 0, 0, 0, 5, 0, 112],
		[7, 0, 0, 0, 5, 0, 141],
		[10, 0, 0, 0, 5, 0, 141],
		[12, 0, 13, 0, 5, 0, 141],
		[12, 9, 13, 0, 5, 0, 141],
		[21, 0, 0, 0, 5, 0, 141],
		[7, 0, 0, 0, 5, 0, 174],
		[12, 0, 13, 0, 5, 0, 174],
		[12, 0, 0, 0, 5, 0, 174],
		[12, 9, 13, 0, 5, 0, 174],
		[10, 0, 0, 0, 5, 0, 174],
		[21, 0, 0, 0, 5, 0, 174],
		[7, 0, 0, 0, 5, 0, 160],
		[12, 0, 13, 0, 5, 0, 160],
		[10, 0, 0, 0, 5, 0, 160],
		[12, 9, 13, 0, 5, 0, 160],
		[21, 0, 0, 0, 5, 0, 160],
		[7, 0, 0, 0, 5, 0, 154],
		[7, 0, 0, 0, 5, 0, 161],
		[21, 0, 0, 0, 5, 0, 161],
		[13, 0, 0, 0, 5, 0, 161],
		[7, 0, 0, 0, 5, 0, 108],
		[10, 0, 0, 0, 5, 0, 108],
		[12, 0, 13, 0, 5, 0, 108],
		[12, 9, 0, 0, 5, 0, 108],
		[21, 0, 0, 0, 5, 0, 108],
		[13, 0, 0, 0, 5, 0, 108],
		[15, 0, 0, 0, 5, 0, 108],
		[21, 0, 0, 0, 5, 0, 132],
		[7, 0, 0, 0, 5, 0, 132],
		[12, 0, 13, 0, 5, 0, 132],
		[10, 0, 0, 0, 5, 0, 132],
		[7, 0, 0, 0, 5, 0, 133],
		[12, 0, 13, 0, 5, 0, 133],
		[12, 7, 13, 0, 5, 0, 133],
		[12, 9, 13, 0, 5, 0, 133],
		[13, 0, 0, 0, 5, 0, 133],
		[7, 0, 0, 0, 5, 0, 119],
		[10, 0, 0, 0, 5, 0, 119],
		[12, 0, 13, 0, 5, 0, 119],
		[12, 9, 13, 0, 5, 0, 119],
		[13, 0, 0, 0, 5, 0, 119],
		[7, 0, 0, 0, 5, 0, 167],
		[6, 0, 0, 0, 5, 0, 167],
		[13, 0, 0, 0, 5, 0, 167],
		[7, 0, 0, 0, 5, 0, 130],
		[12, 0, 13, 0, 5, 0, 130],
		[10, 0, 0, 0, 5, 0, 130],
		[21, 0, 0, 0, 5, 0, 130],
		[12, 0, 13, 0, 5, 0, 123],
		[7, 0, 0, 0, 5, 0, 123],
		[10, 0, 0, 0, 5, 0, 123],
		[10, 9, 0, 0, 5, 0, 123],
		[12, 9, 13, 0, 5, 0, 123],
		[21, 0, 0, 0, 5, 0, 123],
		[13, 0, 0, 0, 5, 0, 123],
		[21, 0, 0, 0, 5, 0, 14],
		[7, 0, 0, 0, 5, 0, 62],
		[14, 0, 0, 0, 5, 0, 62],
		[21, 0, 0, 0, 5, 0, 62],
		[7, 0, 0, 0, 5, 0, 111],
		[21, 0, 0, 0, 5, 0, 111],
		[7, 0, 0, 0, 5, 0, 80],
		[1, 0, 0, 0, 5, 0, 80],
		[12, 0, 13, 0, 5, 0, 80],
		[7, 0, 0, 0, 5, 0, 105],
		[7, 0, 0, 0, 5, 0, 120],
		[12, 0, 13, 0, 5, 0, 120],
		[10, 0, 0, 0, 5, 0, 120],
		[12, 9, 13, 0, 5, 0, 120],
		[13, 0, 0, 0, 5, 0, 120],
		[7, 0, 0, 0, 5, 0, 137],
		[13, 0, 0, 0, 5, 0, 137],
		[21, 0, 0, 0, 5, 0, 137],
		[7, 0, 0, 0, 5, 0, 163],
		[13, 0, 0, 0, 5, 0, 163],
		[7, 0, 0, 0, 5, 0, 106],
		[12, 1, 13, 0, 5, 0, 106],
		[21, 0, 0, 0, 5, 0, 106],
		[7, 0, 0, 0, 5, 0, 152],
		[12, 230, 13, 0, 5, 0, 152],
		[21, 0, 0, 0, 5, 0, 152],
		[26, 0, 0, 0, 5, 0, 152],
		[6, 0, 0, 0, 5, 0, 152],
		[13, 0, 0, 0, 5, 0, 152],
		[15, 0, 0, 0, 5, 0, 152],
		[6, 0, 0, 0, 5, 0, 127],
		[7, 0, 0, 0, 5, 0, 127],
		[21, 0, 0, 0, 5, 0, 127],
		[13, 0, 0, 0, 5, 0, 127],
		[9, 0, 0, 0, 5, 0, 134],
		[5, 0, 0, 0, 5, 0, 134],
		[15, 0, 0, 0, 5, 0, 134],
		[21, 0, 0, 0, 5, 0, 134],
		[9, 0, 0, 0, 5, 0, 107],
		[5, 0, 0, 0, 5, 0, 107],
		[7, 0, 0, 0, 5, 0, 98],
		[12, 0, 13, 0, 5, 0, 98],
		[10, 0, 0, 0, 5, 0, 98],
		[6, 0, 0, 0, 5, 0, 98],
		[6, 0, 0, 0, 2, 0, 164],
		[6, 0, 0, 0, 2, 0, 143],
		[21, 0, 18, 0, 2, 0, 35],
		[12, 0, 13, 0, 2, 0, 124],
		[10, 6, 0, 0, 2, 0, 35],
		[7, 0, 0, 0, 2, 0, 164],
		[7, 0, 0, 0, 2, 0, 124],
		[7, 0, 0, 0, 2, 0, 143],
		[7, 0, 0, 0, 5, 0, 114],
		[26, 0, 0, 0, 5, 0, 114],
		[12, 0, 13, 0, 5, 0, 114],
		[12, 1, 13, 0, 5, 0, 114],
		[21, 0, 0, 0, 5, 0, 114],
		[13, 0, 8, 0, 5, 0, 0],
		[12, 0, 13, 0, 5, 0, 40],
		[10, 216, 0, 0, 5, 0, 0],
		[10, 226, 0, 0, 5, 0, 0],
		[12, 230, 13, 0, 5, 0, 2],
		[26, 0, 0, 0, 5, 0, 158],
		[12, 0, 13, 0, 5, 0, 158],
		[21, 0, 0, 0, 5, 0, 158],
		[12, 230, 13, 0, 5, 0, 56],
		[7, 0, 0, 0, 5, 0, 144],
		[12, 230, 13, 0, 5, 0, 144],
		[6, 0, 0, 0, 5, 0, 144],
		[13, 0, 0, 0, 5, 0, 144],
		[26, 0, 0, 0, 5, 0, 144],
		[7, 0, 0, 0, 5, 0, 168],
		[12, 230, 13, 0, 5, 0, 168],
		[7, 0, 0, 0, 5, 0, 171],
		[12, 230, 13, 0, 5, 0, 171],
		[13, 0, 0, 0, 5, 0, 171],
		[23, 0, 10, 0, 5, 0, 171],
		[7, 0, 0, 0, 5, 0, 140],
		[6, 0, 0, 0, 5, 0, 140],
		[12, 232, 13, 0, 5, 0, 140],
		[12, 220, 13, 0, 5, 0, 140],
		[12, 230, 13, 0, 5, 0, 140],
		[13, 0, 0, 0, 5, 0, 140],
		[7, 0, 0, 0, 5, 0, 145],
		[12, 230, 13, 0, 5, 0, 145],
		[12, 220, 13, 0, 5, 0, 145],
		[13, 0, 0, 0, 5, 0, 145],
		[21, 0, 0, 0, 5, 0, 145],
		[7, 0, 0, 0, 5, 0, 162],
		[12, 230, 13, 0, 5, 0, 162],
		[6, 0, 0, 0, 5, 0, 162],
		[7, 0, 3, 0, 5, 0, 135],
		[15, 0, 3, 0, 5, 0, 135],
		[12, 220, 13, 0, 5, 0, 135],
		[9, 0, 3, 0, 5, 0, 103],
		[5, 0, 3, 0, 5, 0, 103],
		[12, 230, 13, 0, 5, 0, 103],
		[12, 7, 13, 0, 5, 0, 103],
		[6, 0, 3, 0, 5, 0, 103],
		[13, 0, 3, 0, 5, 0, 103],
		[21, 0, 3, 0, 5, 0, 103],
		[15, 0, 4, 0, 5, 0, 0],
		[26, 0, 4, 0, 5, 0, 0],
		[23, 0, 4, 0, 5, 0, 0],
		[26, 0, 0, 0, 2, 0, 32],
	];

	/* Mirror unicode characters. Bidirectional Algorithm, at http://www.unicode.org/unicode/reports/tr9/  */

	public static $mirror_pairs = [
		40 => 41,
		41 => 40,
		60 => 62,
		62 => 60,
		91 => 93,
		93 => 91,
		123 => 125,
		125 => 123,
		171 => 187,
		187 => 171,
		3898 => 3899,
		3899 => 3898,
		3900 => 3901,
		3901 => 3900,
		5787 => 5788,
		5788 => 5787,
		8249 => 8250,
		8250 => 8249,
		8261 => 8262,
		8262 => 8261,
		8317 => 8318,
		8318 => 8317,
		8333 => 8334,
		8334 => 8333,
		8712 => 8715,
		8713 => 8716,
		8714 => 8717,
		8715 => 8712,
		8716 => 8713,
		8717 => 8714,
		8725 => 10741,
		8735 => 11262,
		8736 => 10659,
		8737 => 10651,
		8738 => 10656,
		8740 => 10990,
		8764 => 8765,
		8765 => 8764,
		8771 => 8909,
		8773 => 8780,
		8780 => 8773,
		8786 => 8787,
		8787 => 8786,
		8788 => 8789,
		8789 => 8788,
		8804 => 8805,
		8805 => 8804,
		8806 => 8807,
		8807 => 8806,
		8808 => 8809,
		8809 => 8808,
		8810 => 8811,
		8811 => 8810,
		8814 => 8815,
		8815 => 8814,
		8816 => 8817,
		8817 => 8816,
		8818 => 8819,
		8819 => 8818,
		8820 => 8821,
		8821 => 8820,
		8822 => 8823,
		8823 => 8822,
		8824 => 8825,
		8825 => 8824,
		8826 => 8827,
		8827 => 8826,
		8828 => 8829,
		8829 => 8828,
		8830 => 8831,
		8831 => 8830,
		8832 => 8833,
		8833 => 8832,
		8834 => 8835,
		8835 => 8834,
		8836 => 8837,
		8837 => 8836,
		8838 => 8839,
		8839 => 8838,
		8840 => 8841,
		8841 => 8840,
		8842 => 8843,
		8843 => 8842,
		8847 => 8848,
		8848 => 8847,
		8849 => 8850,
		8850 => 8849,
		8856 => 10680,
		8866 => 8867,
		8867 => 8866,
		8870 => 10974,
		8872 => 10980,
		8873 => 10979,
		8875 => 10981,
		8880 => 8881,
		8881 => 8880,
		8882 => 8883,
		8883 => 8882,
		8884 => 8885,
		8885 => 8884,
		8886 => 8887,
		8887 => 8886,
		8888 => 10204,
		8905 => 8906,
		8906 => 8905,
		8907 => 8908,
		8908 => 8907,
		8909 => 8771,
		8912 => 8913,
		8913 => 8912,
		8918 => 8919,
		8919 => 8918,
		8920 => 8921,
		8921 => 8920,
		8922 => 8923,
		8923 => 8922,
		8924 => 8925,
		8925 => 8924,
		8926 => 8927,
		8927 => 8926,
		8928 => 8929,
		8929 => 8928,
		8930 => 8931,
		8931 => 8930,
		8932 => 8933,
		8933 => 8932,
		8934 => 8935,
		8935 => 8934,
		8936 => 8937,
		8937 => 8936,
		8938 => 8939,
		8939 => 8938,
		8940 => 8941,
		8941 => 8940,
		8944 => 8945,
		8945 => 8944,
		8946 => 8954,
		8947 => 8955,
		8948 => 8956,
		8950 => 8957,
		8951 => 8958,
		8954 => 8946,
		8955 => 8947,
		8956 => 8948,
		8957 => 8950,
		8958 => 8951,
		8968 => 8969,
		8969 => 8968,
		8970 => 8971,
		8971 => 8970,
		9001 => 9002,
		9002 => 9001,
		10088 => 10089,
		10089 => 10088,
		10090 => 10091,
		10091 => 10090,
		10092 => 10093,
		10093 => 10092,
		10094 => 10095,
		10095 => 10094,
		10096 => 10097,
		10097 => 10096,
		10098 => 10099,
		10099 => 10098,
		10100 => 10101,
		10101 => 10100,
		10179 => 10180,
		10180 => 10179,
		10181 => 10182,
		10182 => 10181,
		10184 => 10185,
		10185 => 10184,
		10187 => 10189,
		10189 => 10187,
		10197 => 10198,
		10198 => 10197,
		10204 => 8888,
		10205 => 10206,
		10206 => 10205,
		10210 => 10211,
		10211 => 10210,
		10212 => 10213,
		10213 => 10212,
		10214 => 10215,
		10215 => 10214,
		10216 => 10217,
		10217 => 10216,
		10218 => 10219,
		10219 => 10218,
		10220 => 10221,
		10221 => 10220,
		10222 => 10223,
		10223 => 10222,
		10627 => 10628,
		10628 => 10627,
		10629 => 10630,
		10630 => 10629,
		10631 => 10632,
		10632 => 10631,
		10633 => 10634,
		10634 => 10633,
		10635 => 10636,
		10636 => 10635,
		10637 => 10640,
		10638 => 10639,
		10639 => 10638,
		10640 => 10637,
		10641 => 10642,
		10642 => 10641,
		10643 => 10644,
		10644 => 10643,
		10645 => 10646,
		10646 => 10645,
		10647 => 10648,
		10648 => 10647,
		10651 => 8737,
		10656 => 8738,
		10659 => 8736,
		10660 => 10661,
		10661 => 10660,
		10664 => 10665,
		10665 => 10664,
		10666 => 10667,
		10667 => 10666,
		10668 => 10669,
		10669 => 10668,
		10670 => 10671,
		10671 => 10670,
		10680 => 8856,
		10688 => 10689,
		10689 => 10688,
		10692 => 10693,
		10693 => 10692,
		10703 => 10704,
		10704 => 10703,
		10705 => 10706,
		10706 => 10705,
		10708 => 10709,
		10709 => 10708,
		10712 => 10713,
		10713 => 10712,
		10714 => 10715,
		10715 => 10714,
		10728 => 10729,
		10729 => 10728,
		10741 => 8725,
		10744 => 10745,
		10745 => 10744,
		10748 => 10749,
		10749 => 10748,
		10795 => 10796,
		10796 => 10795,
		10797 => 10798,
		10798 => 10797,
		10804 => 10805,
		10805 => 10804,
		10812 => 10813,
		10813 => 10812,
		10852 => 10853,
		10853 => 10852,
		10873 => 10874,
		10874 => 10873,
		10875 => 10876,
		10876 => 10875,
		10877 => 10878,
		10878 => 10877,
		10879 => 10880,
		10880 => 10879,
		10881 => 10882,
		10882 => 10881,
		10883 => 10884,
		10884 => 10883,
		10885 => 10886,
		10886 => 10885,
		10887 => 10888,
		10888 => 10887,
		10889 => 10890,
		10890 => 10889,
		10891 => 10892,
		10892 => 10891,
		10893 => 10894,
		10894 => 10893,
		10895 => 10896,
		10896 => 10895,
		10897 => 10898,
		10898 => 10897,
		10899 => 10900,
		10900 => 10899,
		10901 => 10902,
		10902 => 10901,
		10903 => 10904,
		10904 => 10903,
		10905 => 10906,
		10906 => 10905,
		10907 => 10908,
		10908 => 10907,
		10909 => 10910,
		10910 => 10909,
		10911 => 10912,
		10912 => 10911,
		10913 => 10914,
		10914 => 10913,
		10918 => 10919,
		10919 => 10918,
		10920 => 10921,
		10921 => 10920,
		10922 => 10923,
		10923 => 10922,
		10924 => 10925,
		10925 => 10924,
		10927 => 10928,
		10928 => 10927,
		10929 => 10930,
		10930 => 10929,
		10931 => 10932,
		10932 => 10931,
		10933 => 10934,
		10934 => 10933,
		10935 => 10936,
		10936 => 10935,
		10937 => 10938,
		10938 => 10937,
		10939 => 10940,
		10940 => 10939,
		10941 => 10942,
		10942 => 10941,
		10943 => 10944,
		10944 => 10943,
		10945 => 10946,
		10946 => 10945,
		10947 => 10948,
		10948 => 10947,
		10949 => 10950,
		10950 => 10949,
		10951 => 10952,
		10952 => 10951,
		10953 => 10954,
		10954 => 10953,
		10955 => 10956,
		10956 => 10955,
		10957 => 10958,
		10958 => 10957,
		10959 => 10960,
		10960 => 10959,
		10961 => 10962,
		10962 => 10961,
		10963 => 10964,
		10964 => 10963,
		10965 => 10966,
		10966 => 10965,
		10974 => 8870,
		10979 => 8873,
		10980 => 8872,
		10981 => 8875,
		10988 => 10989,
		10989 => 10988,
		10990 => 8740,
		10999 => 11000,
		11000 => 10999,
		11001 => 11002,
		11002 => 11001,
		11262 => 8735,
		11778 => 11779,
		11779 => 11778,
		11780 => 11781,
		11781 => 11780,
		11785 => 11786,
		11786 => 11785,
		11788 => 11789,
		11789 => 11788,
		11804 => 11805,
		11805 => 11804,
		11808 => 11809,
		11809 => 11808,
		11810 => 11811,
		11811 => 11810,
		11812 => 11813,
		11813 => 11812,
		11814 => 11815,
		11815 => 11814,
		11816 => 11817,
		11817 => 11816,
		11861 => 11862,
		11862 => 11861,
		11863 => 11864,
		11864 => 11863,
		11865 => 11866,
		11866 => 11865,
		11867 => 11868,
		11868 => 11867,
		12296 => 12297,
		12297 => 12296,
		12298 => 12299,
		12299 => 12298,
		12300 => 12301,
		12301 => 12300,
		12302 => 12303,
		12303 => 12302,
		12304 => 12305,
		12305 => 12304,
		12308 => 12309,
		12309 => 12308,
		12310 => 12311,
		12311 => 12310,
		12312 => 12313,
		12313 => 12312,
		12314 => 12315,
		12315 => 12314,
		65113 => 65114,
		65114 => 65113,
		65115 => 65116,
		65116 => 65115,
		65117 => 65118,
		65118 => 65117,
		65124 => 65125,
		65125 => 65124,
		65288 => 65289,
		65289 => 65288,
		65308 => 65310,
		65310 => 65308,
		65339 => 65341,
		65341 => 65339,
		65371 => 65373,
		65373 => 65371,
		65375 => 65376,
		65376 => 65375,
		65378 => 65379,
		65379 => 65378,
	];


	/* index tables for the database records */

	private static $index0 = [
		0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26,
		27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50,
		51, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 53, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 54, 55, 55, 55, 56, 57, 58, 59, 60, 61, 62,
		63, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64,
		64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 65, 66, 66, 66,
		66, 66, 66, 66, 66, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 52, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84,
		85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106,
		106, 106, 107, 108, 109, 110, 110, 110, 110, 110, 110, 110, 110, 110, 111, 112, 112, 112, 112,
		113, 112, 112, 112, 112, 112, 112, 112, 112, 112, 112, 112, 112, 112, 112, 114, 115, 115, 116,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 117, 110, 110, 110, 110, 110, 110, 118, 118, 119, 120, 110,
		121, 122, 123, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124,
		124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 124, 125, 126, 127, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 128, 129, 130, 131, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 132, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 145, 146, 110, 110, 110, 110,
		147, 148, 149, 150, 110, 151, 152, 153, 154, 155, 156, 157, 157, 158, 159, 160, 157, 161, 162,
		163, 164, 165, 166, 167, 168, 169, 170, 171, 172, 110, 110, 110, 173, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 174, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 175, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 176, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 177, 52, 52, 178, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 52, 52, 180, 179, 179, 179, 179, 181, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 182, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 183, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179, 179,
		179, 179, 179, 179, 179, 179, 179, 181, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 173, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 173, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 173, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 173, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 173, 184, 185, 186, 186, 186, 186, 186, 186, 186, 186, 186, 186, 186, 186, 186, 186,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110,
		110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 110, 173, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 187, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 187,
	];

	private static $index1 = [
		0, 1, 0, 2, 3, 4, 5, 6, 7, 8, 8, 9, 10, 11, 11, 12, 13, 0, 0, 0, 14, 15, 16, 17, 18, 19, 20, 21,
		22, 23, 24, 25, 26, 27, 28, 29, 30, 29, 31, 32, 33, 34, 35, 27, 30, 29, 27, 36, 37, 38, 39, 40,
		41, 42, 43, 44, 45, 46, 47, 48, 27, 27, 49, 27, 27, 27, 27, 27, 27, 27, 50, 51, 52, 27, 53, 54,
		53, 54, 54, 54, 54, 54, 55, 54, 54, 54, 56, 57, 58, 59, 60, 61, 62, 63, 64, 64, 65, 65, 66, 67,
		68, 69, 70, 71, 72, 73, 74, 75, 76, 65, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
		91, 92, 93, 94, 95, 96, 97, 97, 97, 97, 98, 98, 98, 98, 99, 100, 101, 101, 101, 101, 102, 103,
		101, 101, 101, 101, 101, 101, 104, 105, 101, 101, 101, 101, 101, 101, 101, 101, 101, 101, 101,
		101, 106, 107, 107, 107, 108, 109, 110, 110, 110, 110, 110, 111, 112, 113, 114, 115, 116, 117,
		118, 119, 120, 120, 120, 121, 122, 119, 123, 124, 125, 126, 127, 127, 127, 127, 128, 129, 130,
		131, 132, 133, 134, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 135, 136, 137, 138,
		139, 140, 141, 142, 143, 144, 144, 144, 145, 146, 147, 148, 127, 127, 127, 127, 127, 127, 149,
		149, 149, 149, 150, 151, 152, 153, 154, 155, 156, 156, 156, 157, 158, 159, 160, 160, 161, 162,
		163, 164, 165, 166, 167, 167, 167, 168, 144, 169, 127, 127, 127, 170, 171, 172, 127, 127, 127,
		127, 127, 173, 174, 125, 175, 176, 177, 178, 179, 180, 180, 180, 180, 180, 180, 181, 182, 183,
		184, 180, 185, 186, 187, 180, 188, 189, 190, 191, 191, 192, 193, 194, 195, 196, 197, 198, 199,
		200, 201, 202, 203, 204, 205, 206, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217,
		218, 219, 220, 221, 221, 222, 223, 224, 225, 226, 227, 217, 228, 229, 230, 231, 232, 233, 234,
		235, 235, 236, 237, 238, 239, 240, 241, 242, 243, 244, 245, 217, 246, 247, 248, 249, 250, 247,
		251, 252, 253, 254, 255, 217, 256, 257, 258, 259, 260, 261, 262, 263, 263, 262, 263, 264, 265,
		266, 267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 276, 275, 277, 278, 279, 280, 281, 282,
		283, 284, 285, 217, 286, 287, 288, 289, 289, 289, 289, 290, 291, 292, 293, 294, 295, 296, 297,
		298, 299, 300, 301, 302, 300, 300, 303, 304, 301, 305, 306, 307, 308, 309, 310, 217, 311, 312,
		312, 312, 312, 312, 313, 314, 315, 316, 317, 318, 217, 217, 217, 217, 319, 320, 321, 321, 322,
		321, 323, 324, 325, 326, 327, 328, 217, 217, 217, 217, 329, 330, 331, 332, 333, 334, 335, 336,
		337, 338, 337, 337, 337, 339, 340, 341, 342, 343, 344, 345, 344, 344, 344, 346, 347, 348, 349,
		350, 217, 217, 217, 217, 351, 351, 351, 351, 351, 352, 353, 354, 355, 356, 357, 358, 359, 360,
		361, 351, 362, 363, 355, 364, 365, 365, 365, 365, 366, 367, 368, 368, 368, 368, 368, 369, 370,
		370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 371, 371, 371, 371, 371, 371, 371, 371,
		371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 372, 372, 372, 372, 372, 372, 372,
		372, 372, 373, 374, 373, 372, 372, 372, 372, 372, 373, 372, 372, 372, 372, 373, 374, 373, 372,
		374, 372, 372, 372, 372, 372, 372, 372, 373, 372, 372, 372, 372, 372, 372, 372, 372, 375, 376,
		377, 378, 379, 372, 372, 380, 381, 382, 382, 382, 382, 382, 382, 382, 382, 382, 382, 383, 384,
		385, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386,
		386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386,
		386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386,
		386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386,
		386, 387, 386, 386, 388, 389, 389, 390, 391, 391, 391, 391, 391, 391, 391, 391, 391, 392, 393,
		394, 395, 395, 396, 397, 398, 398, 399, 217, 400, 400, 401, 217, 402, 403, 404, 217, 405, 405,
		405, 405, 405, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 419, 420,
		420, 420, 420, 421, 420, 420, 420, 420, 420, 420, 422, 423, 420, 420, 420, 420, 424, 386, 386,
		386, 386, 386, 386, 386, 386, 425, 217, 426, 426, 426, 427, 428, 429, 430, 431, 432, 433, 434,
		434, 434, 435, 436, 217, 437, 437, 437, 437, 437, 438, 437, 437, 437, 439, 440, 441, 442, 442,
		442, 442, 443, 443, 444, 445, 446, 446, 446, 446, 446, 446, 447, 448, 449, 450, 451, 452, 453,
		454, 453, 454, 455, 456, 457, 458, 459, 460, 461, 462, 463, 464, 217, 217, 465, 466, 466, 466,
		466, 466, 467, 468, 469, 470, 471, 472, 473, 474, 475, 476, 477, 478, 478, 478, 479, 480, 481,
		482, 483, 483, 483, 483, 484, 485, 486, 487, 488, 488, 488, 488, 489, 490, 491, 492, 493, 494,
		495, 496, 497, 497, 497, 498, 100, 499, 365, 365, 365, 365, 365, 500, 501, 217, 502, 503, 504,
		505, 506, 507, 54, 54, 54, 54, 508, 509, 56, 56, 56, 56, 56, 510, 511, 512, 54, 513, 54, 54, 54,
		514, 56, 56, 56, 515, 460, 516, 517, 461, 461, 461, 518, 519, 27, 27, 27, 27, 27, 27, 27, 27, 27,
		27, 27, 27, 27, 27, 27, 27, 27, 27, 520, 521, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 522,
		523, 524, 525, 522, 523, 522, 523, 524, 525, 522, 526, 522, 523, 522, 524, 522, 527, 522, 527,
		522, 527, 528, 529, 530, 531, 532, 533, 522, 534, 535, 536, 537, 538, 539, 540, 541, 542, 543,
		544, 545, 546, 547, 548, 549, 550, 551, 552, 553, 554, 56, 555, 556, 557, 556, 556, 558, 559, 560,
		561, 562, 563, 564, 217, 565, 566, 567, 568, 569, 570, 571, 572, 573, 574, 575, 576, 577, 578,
		577, 579, 580, 581, 582, 583, 584, 585, 586, 587, 586, 588, 589, 586, 590, 586, 591, 592, 593,
		594, 595, 596, 597, 598, 599, 600, 601, 602, 603, 604, 605, 606, 601, 601, 607, 608, 609, 610,
		611, 601, 601, 612, 592, 613, 614, 601, 601, 615, 601, 601, 586, 616, 617, 618, 619, 620, 621,
		622, 622, 622, 622, 622, 622, 622, 622, 623, 586, 586, 624, 625, 592, 592, 626, 586, 586, 586,
		586, 591, 627, 628, 629, 586, 586, 586, 586, 586, 586, 630, 217, 217, 586, 631, 217, 217, 632,
		632, 632, 632, 632, 633, 633, 634, 635, 635, 635, 635, 635, 635, 635, 635, 635, 636, 632, 632,
		637, 637, 637, 637, 637, 637, 637, 637, 637, 638, 637, 637, 637, 637, 638, 586, 637, 637, 639,
		586, 640, 587, 641, 642, 643, 644, 587, 586, 639, 590, 586, 645, 646, 647, 648, 649, 586, 586,
		650, 586, 651, 650, 652, 586, 653, 654, 586, 655, 586, 656, 657, 658, 659, 660, 586, 661, 662,
		663, 664, 637, 665, 666, 667, 668, 669, 618, 586, 586, 586, 670, 586, 671, 586, 672, 673, 586,
		586, 674, 675, 632, 676, 676, 677, 586, 586, 586, 670, 655, 678, 679, 680, 681, 682, 683, 592,
		592, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684,
		684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 684, 592, 592, 592, 592, 592,
		592, 592, 592, 592, 592, 592, 592, 592, 592, 592, 592, 685, 686, 686, 687, 688, 601, 592, 689,
		615, 690, 691, 692, 693, 694, 695, 696, 592, 697, 601, 698, 699, 700, 701, 681, 592, 592, 604,
		689, 701, 702, 703, 704, 601, 601, 601, 601, 705, 706, 601, 601, 601, 601, 707, 708, 709, 681,
		710, 711, 586, 586, 586, 712, 586, 586, 592, 592, 713, 714, 715, 587, 586, 586, 716, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 717, 718, 718, 718, 718,
		718, 718, 719, 719, 719, 719, 719, 719, 720, 721, 722, 723, 92, 92, 92, 92, 92, 92, 92, 92, 92,
		92, 92, 92, 724, 725, 726, 727, 368, 368, 368, 368, 728, 729, 730, 730, 730, 730, 730, 730, 730,
		731, 732, 733, 372, 372, 374, 217, 374, 374, 374, 374, 374, 374, 374, 374, 734, 734, 734, 734,
		735, 736, 737, 738, 739, 740, 546, 741, 742, 546, 743, 744, 217, 217, 217, 217, 745, 745, 745,
		746, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 747, 217, 745, 745, 745, 745, 745, 745,
		745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745, 745,
		745, 748, 217, 217, 217, 650, 650, 749, 750, 751, 752, 753, 754, 755, 756, 757, 758, 758, 758,
		758, 758, 758, 758, 758, 758, 759, 760, 761, 762, 762, 762, 762, 762, 762, 762, 762, 762, 762,
		763, 764, 765, 765, 765, 765, 765, 766, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 767,
		768, 769, 765, 765, 765, 765, 650, 650, 650, 650, 770, 771, 762, 762, 772, 772, 772, 773, 774,
		775, 769, 769, 769, 776, 777, 778, 772, 772, 772, 779, 774, 775, 769, 769, 769, 769, 780, 778,
		769, 781, 782, 782, 782, 782, 782, 783, 782, 782, 782, 782, 782, 782, 782, 782, 782, 782, 782,
		769, 769, 769, 784, 785, 769, 769, 769, 769, 769, 769, 769, 769, 769, 769, 769, 786, 769, 769,
		769, 784, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 650, 650, 650, 650, 650, 650, 650, 650, 788, 788, 789, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 790, 791, 791, 791, 791,
		791, 791, 792, 217, 793, 793, 793, 793, 793, 794, 795, 795, 795, 795, 795, 795, 795, 795, 795,
		795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795, 795,
		795, 795, 795, 795, 795, 796, 795, 795, 797, 798, 217, 217, 101, 101, 101, 101, 101, 799, 800,
		801, 101, 101, 101, 802, 803, 803, 803, 803, 803, 803, 803, 803, 804, 805, 806, 217, 64, 64, 807,
		808, 809, 27, 810, 27, 27, 27, 27, 27, 27, 27, 811, 812, 27, 813, 814, 27, 27, 815, 816, 27, 817,
		818, 27, 819, 217, 217, 820, 821, 822, 823, 824, 824, 825, 826, 827, 828, 829, 829, 829, 829, 829,
		829, 830, 217, 831, 832, 832, 832, 832, 832, 833, 834, 835, 836, 837, 838, 839, 839, 840, 841,
		842, 843, 844, 844, 845, 846, 847, 847, 848, 849, 850, 851, 370, 370, 370, 852, 853, 854, 854,
		854, 854, 854, 855, 856, 857, 858, 859, 860, 861, 351, 355, 862, 863, 863, 863, 863, 863, 864,
		865, 217, 866, 867, 868, 869, 351, 351, 870, 871, 872, 872, 872, 872, 872, 872, 873, 874, 875,
		217, 217, 876, 877, 878, 879, 217, 880, 880, 880, 217, 374, 374, 54, 54, 54, 54, 54, 881, 882,
		883, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884, 877, 877, 877, 877, 885, 886, 887, 888,
		370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370,
		370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370,
		370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 370, 889, 217, 371, 371, 890,
		891, 371, 371, 371, 371, 371, 892, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893,
		893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893,
		893, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894,
		894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 895, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 896, 897, 897, 897, 897, 898, 217, 899, 900, 120, 901, 902, 903, 904, 120, 127,
		127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 905, 906, 907, 908, 909, 127, 127, 127,
		127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127,
		127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127,
		127, 127, 127, 910, 908, 908, 127, 127, 127, 127, 127, 127, 127, 127, 911, 127, 127, 127, 127,
		127, 127, 908, 912, 912, 912, 912, 127, 913, 914, 914, 915, 916, 917, 918, 919, 920, 921, 922,
		923, 924, 925, 926, 927, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127, 127,
		127, 127, 928, 929, 930, 931, 932, 933, 934, 934, 935, 936, 937, 937, 938, 939, 940, 941, 940,
		940, 940, 940, 942, 943, 943, 943, 944, 945, 945, 945, 946, 947, 948, 912, 949, 950, 951, 950,
		950, 952, 950, 950, 953, 950, 954, 950, 954, 217, 217, 217, 217, 950, 950, 950, 950, 950, 950,
		950, 950, 950, 950, 950, 950, 950, 950, 950, 955, 956, 957, 957, 957, 957, 957, 958, 622, 959,
		959, 959, 959, 959, 959, 960, 961, 962, 963, 586, 964, 965, 217, 217, 217, 217, 217, 622, 622,
		622, 622, 622, 966, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 967, 967, 967, 968, 969, 969, 969, 969, 969, 969, 970, 217, 971, 972, 972, 973, 974, 974,
		974, 974, 975, 976, 977, 977, 978, 979, 980, 980, 980, 980, 981, 982, 983, 983, 983, 984, 985,
		985, 985, 985, 986, 985, 987, 217, 217, 217, 217, 217, 988, 988, 988, 988, 988, 989, 989, 989,
		989, 989, 990, 990, 990, 990, 990, 990, 991, 991, 991, 992, 993, 994, 995, 995, 995, 995, 996,
		997, 997, 997, 997, 998, 999, 999, 999, 999, 999, 217, 1000, 1000, 1000, 1000, 1000, 1000, 1001,
		1002, 1003, 1004, 1003, 1004, 1005, 1006, 1007, 1006, 1007, 1008, 1009, 1009, 1009, 1009, 1009,
		1009, 1010, 217, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011,
		1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011,
		1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1011, 1012, 217, 1011, 1011, 1013, 217, 1011, 217,
		217, 217, 1014, 56, 56, 56, 56, 56, 1015, 1016, 217, 217, 217, 217, 217, 217, 217, 217, 1017,
		1018, 1019, 1019, 1019, 1019, 1020, 1021, 1022, 1022, 1023, 1024, 1025, 1025, 1026, 1027, 1028,
		1028, 1028, 1029, 1030, 1031, 119, 119, 119, 119, 119, 119, 1032, 1032, 1033, 1034, 1035, 1035,
		1036, 1037, 1038, 1038, 1038, 1039, 1040, 1040, 1040, 1041, 119, 119, 119, 119, 1042, 1042, 1042,
		1042, 1043, 1043, 1043, 1044, 1045, 1045, 1046, 1045, 1045, 1045, 1045, 1045, 1047, 1048, 1049,
		1050, 1051, 1051, 1052, 1053, 1054, 1055, 1056, 1057, 1058, 1058, 1058, 1059, 1060, 1060, 1060,
		1061, 119, 119, 119, 119, 1062, 1063, 1062, 1062, 1064, 1065, 1066, 119, 1067, 1067, 1067, 1067,
		1067, 1067, 1068, 1069, 1070, 1070, 1071, 1072, 1073, 1073, 1074, 1075, 1076, 1076, 1077, 1078,
		119, 1079, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 1080, 1080, 1080, 1080, 1080, 1080,
		1080, 1080, 1080, 1081, 119, 119, 119, 119, 119, 119, 1082, 1082, 1082, 1082, 1082, 1082, 1083,
		119, 1084, 1084, 1084, 1084, 1084, 1084, 1085, 1086, 1087, 1087, 1087, 1087, 1088, 153, 1089,
		1090, 1091, 1092, 1093, 1093, 1094, 1095, 1096, 1096, 1097, 1098, 119, 119, 119, 119, 119, 119,
		119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119,
		119, 1099, 1099, 1099, 1100, 1101, 1101, 1101, 1101, 1101, 1102, 1103, 119, 1104, 153, 1105, 1106,
		153, 153, 153, 1107, 1108, 1108, 1108, 1109, 1110, 119, 1111, 1111, 1112, 1113, 1114, 1115, 153,
		153, 1116, 1116, 1117, 1118, 119, 119, 119, 119, 1119, 1119, 1120, 1121, 119, 119, 1122, 1122,
		1123, 119, 1124, 1125, 1125, 1125, 1125, 1125, 1125, 1126, 1127, 1128, 1129, 1130, 1131, 1132,
		1133, 1134, 1135, 1136, 1136, 1136, 1136, 1136, 1137, 1138, 1139, 1140, 1141, 1141, 1141, 1142,
		1143, 1144, 1145, 1146, 1146, 1146, 1147, 1148, 1149, 1150, 1151, 217, 1152, 1152, 1152, 1152,
		1153, 217, 1154, 1155, 1155, 1155, 1155, 1155, 1156, 1157, 1158, 1159, 1160, 1161, 1162, 1163,
		1164, 217, 1165, 1165, 1166, 1165, 1165, 1167, 1168, 1169, 1170, 217, 217, 217, 217, 217, 217,
		217, 1171, 1172, 1173, 1174, 1173, 1175, 1176, 1176, 1176, 1176, 1176, 1177, 1178, 1179, 1180,
		1181, 1182, 1183, 1184, 1185, 1185, 1186, 1187, 1188, 1189, 1190, 1191, 1192, 1193, 1194, 1194,
		217, 1195, 1196, 1195, 1195, 1195, 1195, 1197, 1198, 1199, 1200, 1201, 1202, 1203, 217, 217, 217,
		1204, 1204, 1204, 1204, 1204, 1204, 1205, 1206, 1207, 1208, 1209, 1210, 1211, 217, 217, 217, 1212,
		1212, 1212, 1212, 1212, 1212, 1213, 1214, 1215, 217, 1216, 1217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1218, 1218, 1218, 1218,
		1218, 1219, 1220, 1221, 1222, 1223, 1223, 1224, 217, 217, 217, 217, 1225, 1225, 1225, 1225, 1225,
		1225, 1226, 1227, 1228, 217, 1229, 1230, 1231, 1232, 217, 217, 1233, 1233, 1233, 1233, 1233, 1234,
		1235, 1236, 1237, 1238, 355, 355, 1239, 217, 217, 217, 1240, 1240, 1240, 1241, 1242, 1243, 1244,
		1245, 1246, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 1247, 1247, 1247, 1247, 1247, 1248, 1249, 1250, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 1251, 1251, 1251, 1251, 1252, 1252, 1252, 1252, 1253,
		1254, 1255, 1256, 1257, 1258, 1259, 1260, 1260, 1260, 1261, 1262, 1263, 217, 1264, 1265, 217, 217,
		217, 217, 217, 217, 217, 217, 1266, 1267, 1266, 1266, 1266, 1266, 1268, 1269, 1270, 217, 217, 217,
		1271, 1272, 1273, 1273, 1273, 1273, 1274, 1275, 1276, 217, 1277, 1278, 1279, 1279, 1279, 1279,
		1279, 1280, 1281, 1282, 1283, 217, 386, 386, 1284, 1284, 1284, 1284, 1284, 1284, 1284, 1285, 1286,
		1287, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1288, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 1289, 1289, 1289, 1289, 1290, 217, 1291, 1292, 1293, 1294, 1293, 1293, 1293,
		1295, 1296, 1297, 1298, 217, 1299, 1300, 1301, 1302, 1303, 1304, 1304, 1304, 1305, 1306, 1306,
		1307, 1308, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1309, 1310, 1311, 1311, 1311, 1311, 1312,
		1313, 1314, 217, 1315, 1316, 1317, 1318, 1319, 1319, 1319, 1320, 1321, 1322, 1323, 1324, 1325,
		1325, 1325, 1325, 1325, 1326, 1327, 1328, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		1329, 1329, 1330, 1331, 1332, 1333, 1334, 1333, 1333, 1333, 1335, 1336, 1337, 1338, 1339, 1340,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1341, 217, 1342, 1342, 1343, 1344, 1345, 1346,
		1347, 1348, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349,
		1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349,
		1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349,
		1349, 1349, 1349, 1349, 1349, 1350, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1351, 1352, 1353, 217,
		1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349,
		1349, 1349, 1349, 1349, 1349, 1349, 1349, 1349, 1354, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 1355, 1355, 1355, 1355, 1355, 1355, 1355, 1355, 1355, 1355,
		1355, 1355, 1356, 217, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357,
		1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357,
		1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1358, 1358, 1359, 1360, 1361, 217,
		1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357,
		1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357,
		1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357, 1357,
		1357, 1357, 1357, 1362, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363,
		1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363,
		1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1363, 1364, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		1365, 1365, 1365, 1366, 1367, 1368, 1369, 1370, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 803, 803, 803, 803, 803,
		803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803,
		803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 803, 1371, 1372, 1372, 1372,
		1373, 1374, 1375, 1376, 1376, 1376, 1376, 1376, 1376, 1376, 1376, 1376, 1377, 1378, 1379, 1380,
		1380, 1380, 1381, 1382, 217, 1383, 1383, 1383, 1383, 1383, 1383, 1384, 1385, 1386, 217, 1387,
		1388, 1389, 1383, 1383, 1390, 1383, 1383, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1391, 1392, 1392, 1392, 1392, 1393, 1394,
		1395, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 1396, 1396, 1396, 1396, 1397, 1397, 1397, 1397, 1398, 1398, 1399,
		1400, 1401, 1401, 1401, 1402, 1403, 1403, 1404, 217, 217, 217, 217, 217, 1405, 1405, 1405, 1405,
		1405, 1405, 1405, 1405, 1405, 1406, 1407, 1408, 1408, 1408, 1408, 1408, 1408, 1409, 1410, 1411,
		217, 217, 217, 217, 217, 217, 217, 217, 1412, 217, 1413, 217, 1414, 1414, 1414, 1414, 1414, 1414,
		1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414,
		1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1415, 1415, 1415, 1415, 1415, 1415,
		1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415,
		1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415,
		1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415, 1415,
		1415, 1415, 1415, 1415, 1416, 217, 217, 217, 217, 1417, 1414, 1414, 1414, 1418, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414, 1414,
		1414, 1414, 1414, 1414, 1414, 1419, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		1420, 1421, 1422, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758,
		758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758, 758,
		1423, 217, 1424, 217, 217, 217, 1425, 217, 1426, 217, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1428, 1429, 1429, 1429, 1429, 1429,
		1429, 1429, 1429, 1429, 1429, 1429, 1429, 1429, 1430, 1429, 1431, 1429, 1432, 1429, 1433, 1434,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 621,
		622, 622, 622, 1435, 1436, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 1437, 1438, 586, 586, 1439, 217, 586, 586, 1440, 217, 1441, 1441, 1441, 1441, 1441,
		1442, 1441, 1441, 1443, 217, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		1444, 217, 217, 217, 217, 217, 217, 217, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		1445, 217, 622, 622, 622, 622, 1446, 1447, 622, 622, 622, 622, 622, 622, 1448, 1449, 1450, 1451,
		1452, 1453, 622, 622, 622, 1454, 622, 622, 622, 622, 622, 622, 622, 1455, 217, 217, 962, 962, 962,
		962, 962, 962, 962, 962, 1456, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 957, 957, 1457, 217, 957, 957, 1457, 217, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 1458, 217, 774, 774, 1459, 1460, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 1461, 1461, 1461, 1462, 1463, 1463, 1464, 1461, 1461, 1465, 1466, 1463, 1463,
		1461, 1461, 1461, 1462, 1463, 1463, 1467, 1468, 1469, 1465, 1470, 1471, 1463, 1461, 1461, 1461,
		1462, 1463, 1463, 1472, 1473, 1474, 1475, 1463, 1463, 1463, 1476, 1477, 1478, 1479, 1463, 1463,
		1464, 1461, 1461, 1465, 1463, 1463, 1463, 1461, 1461, 1461, 1462, 1463, 1463, 1464, 1461, 1461,
		1465, 1463, 1463, 1463, 1461, 1461, 1461, 1462, 1463, 1463, 1464, 1461, 1461, 1465, 1463, 1463,
		1463, 1461, 1461, 1461, 1462, 1463, 1463, 1480, 1461, 1461, 1461, 1481, 1463, 1463, 1482, 1483,
		1461, 1461, 1484, 1463, 1463, 1485, 1464, 1461, 1461, 1486, 1463, 1463, 1487, 1488, 1461, 1461,
		1489, 1463, 1463, 1463, 1490, 1461, 1461, 1461, 1481, 1463, 1463, 1482, 1491, 1435, 1435, 1435,
		1435, 1435, 1435, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492,
		1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492, 1492,
		1492, 1492, 1492, 1493, 1493, 1493, 1493, 1493, 1493, 1494, 1495, 1493, 1493, 1493, 1493, 1493,
		1496, 1497, 1492, 1498, 1499, 217, 1500, 1501, 1493, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 54, 1502, 54, 898, 1503, 1504, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1505, 1506, 1506, 1507,
		1508, 1509, 1510, 1510, 1510, 1510, 1510, 1510, 1510, 1511, 217, 217, 217, 1512, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1513, 1513, 1513, 1513, 1513, 1514, 1515,
		1516, 1517, 1518, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 1519, 1519, 1519, 1520, 217, 217, 1521, 1521, 1521, 1521, 1521, 1522,
		1523, 1524, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 1525, 1525, 1525, 1526, 1527, 1528, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 1529, 1529, 1529, 1530, 1531, 1532, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1533, 1533, 1533,
		1534, 1535, 1536, 1537, 1538, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 374, 1539, 372, 374,
		1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540,
		1540, 1540, 1540, 1540, 1540, 1540, 1540, 1540, 1541, 1542, 1543, 119, 119, 119, 119, 119, 1544,
		1544, 1544, 1544, 1545, 1546, 1546, 1546, 1547, 1548, 1549, 1550, 119, 119, 119, 119, 119, 119,
		119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119,
		119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119,
		119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119,
		119, 119, 119, 1551, 1552, 1552, 1552, 1552, 1552, 1552, 1553, 1554, 153, 119, 119, 119, 119, 119,
		119, 119, 119, 1551, 1552, 1552, 1552, 1552, 1555, 1552, 1556, 153, 153, 119, 119, 119, 119, 119,
		119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 119, 1557, 127,
		127, 127, 1558, 1559, 1560, 1561, 1562, 1563, 1558, 1564, 1558, 1560, 1560, 1565, 127, 1566, 127,
		1567, 1568, 1566, 127, 1567, 153, 153, 153, 153, 153, 153, 1569, 153, 1570, 586, 586, 586, 586,
		1437, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 1437, 217, 586, 1571, 1572, 586,
		1572, 655, 1572, 586, 586, 586, 1573, 217, 633, 1574, 635, 635, 635, 1575, 635, 635, 635, 635,
		635, 635, 635, 1576, 635, 635, 635, 1577, 1578, 1579, 635, 1580, 217, 217, 217, 217, 217, 217,
		1581, 622, 622, 622, 1582, 217, 769, 769, 769, 769, 769, 1583, 769, 1584, 1585, 217, 770, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 650,
		650, 650, 650, 670, 1586, 1587, 650, 650, 650, 650, 650, 650, 650, 650, 1588, 650, 650, 652, 586,
		650, 650, 650, 650, 650, 1589, 652, 586, 650, 650, 1590, 1591, 650, 650, 650, 650, 650, 650, 650,
		1592, 1593, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 650, 650, 650, 650, 1594, 650, 650, 650, 650, 650, 650, 650, 1595, 586, 1596, 650, 650, 650,
		586, 586, 1597, 586, 586, 1598, 586, 1570, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 1599,
		650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 586, 586, 586, 586, 586, 586, 650, 650, 650,
		650, 650, 650, 650, 650, 1595, 1570, 1600, 1601, 586, 1602, 1603, 1604, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 630, 650, 1605, 1606, 217, 586, 1437, 586, 586, 586, 586, 586, 586, 586, 217, 586,
		630, 586, 586, 586, 586, 586, 217, 586, 586, 586, 1573, 586, 1437, 630, 217, 592, 1440, 217, 217,
		217, 217, 586, 1603, 650, 650, 650, 650, 650, 1607, 1587, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 217, 586, 1573, 650, 1604, 650, 1608, 650, 650, 650, 650, 650,
		650, 1458, 1609, 650, 1610, 650, 1611, 650, 1606, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 1612, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 1435, 1613, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217,
		217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 217, 1614, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 897, 897, 897, 897, 787, 787, 787, 895, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 895, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 1615, 897, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 895, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		787, 787, 787, 895, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 897, 897, 897, 897, 1616, 787, 787, 787, 787, 787, 787, 787, 787, 787, 1617, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 787, 896, 897, 897,
		897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 1618, 912, 912, 912, 1619,
		1619, 1619, 1619, 1619, 1619, 1619, 1619, 1619, 1619, 1619, 1619, 912, 912, 912, 912, 912, 912,
		912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 914, 914, 914, 914, 914, 914, 914, 914, 914,
		914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914,
		914, 914, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912,
		912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 912, 894, 894,
		894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894,
		894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 1620,
	];

	private static $index2 = [
		1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 3, 2, 4, 3, 1, 1, 1, 1, 1, 1, 3, 3, 3, 2, 5, 6, 6, 7, 8, 7, 6, 6, 9,
		10, 6, 11, 12, 13, 12, 12, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 12, 6, 15, 16, 15, 6, 6, 17,
		17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 17, 9, 6, 10, 18, 19, 18, 20, 20,
		20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 20, 9, 16, 10, 16, 1, 1, 1, 1, 1, 1,
		3, 1, 1, 21, 22, 8, 8, 23, 8, 24, 22, 25, 26, 27, 28, 16, 29, 30, 18, 31, 32, 33, 33, 25, 34, 22,
		22, 25, 33, 27, 35, 36, 36, 36, 22, 37, 37, 37, 37, 37, 37, 38, 37, 37, 37, 37, 37, 37, 37, 37,
		37, 38, 37, 37, 37, 37, 37, 37, 39, 38, 37, 37, 37, 37, 37, 38, 40, 40, 40, 41, 41, 41, 41, 40,
		41, 40, 40, 40, 41, 40, 40, 41, 41, 40, 41, 40, 40, 41, 41, 41, 39, 40, 40, 40, 41, 40, 41, 40,
		41, 37, 40, 37, 41, 37, 41, 37, 41, 37, 41, 37, 41, 37, 41, 37, 41, 37, 40, 37, 40, 37, 41, 37,
		41, 37, 41, 37, 40, 37, 41, 37, 41, 37, 41, 37, 41, 37, 41, 38, 40, 37, 40, 38, 40, 37, 41, 37,
		41, 40, 37, 41, 37, 41, 37, 41, 38, 40, 38, 40, 37, 40, 37, 41, 37, 40, 40, 38, 40, 37, 40, 37,
		41, 37, 41, 38, 40, 37, 41, 37, 41, 37, 37, 41, 37, 41, 37, 41, 41, 41, 37, 37, 41, 37, 41, 37,
		37, 41, 37, 37, 37, 41, 41, 37, 37, 37, 37, 41, 37, 37, 41, 37, 37, 37, 41, 41, 41, 37, 37, 41,
		37, 37, 41, 37, 41, 37, 41, 37, 37, 41, 37, 41, 41, 37, 41, 37, 37, 41, 37, 37, 37, 41, 37, 41,
		37, 37, 41, 41, 42, 37, 41, 41, 41, 42, 42, 42, 42, 37, 43, 41, 37, 43, 41, 37, 43, 41, 37, 40,
		37, 40, 37, 40, 37, 40, 37, 40, 37, 40, 37, 40, 37, 40, 41, 37, 41, 41, 37, 43, 41, 37, 41, 37,
		37, 37, 41, 37, 41, 41, 41, 41, 41, 41, 41, 37, 37, 41, 37, 37, 41, 41, 37, 41, 37, 37, 37, 37,
		41, 41, 40, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 41, 42, 42, 41,
		41, 44, 44, 44, 44, 44, 44, 44, 44, 44, 45, 45, 46, 46, 46, 46, 46, 46, 46, 47, 47, 25, 47, 45,
		48, 45, 48, 48, 48, 45, 48, 45, 45, 49, 46, 47, 47, 47, 47, 47, 47, 25, 25, 25, 25, 47, 25, 47,
		25, 44, 44, 44, 44, 44, 47, 47, 47, 47, 47, 50, 50, 45, 47, 46, 47, 47, 47, 47, 47, 47, 47, 47,
		47, 51, 51, 51, 51, 51, 51, 51, 51, 51, 51, 51, 51, 51, 52, 53, 53, 53, 53, 52, 54, 53, 53, 53,
		53, 53, 55, 55, 53, 53, 53, 53, 55, 55, 53, 53, 53, 53, 53, 53, 53, 53, 53, 53, 53, 56, 56, 56,
		56, 56, 53, 53, 53, 53, 51, 51, 51, 51, 51, 51, 51, 51, 57, 51, 53, 53, 53, 51, 51, 51, 53, 53,
		58, 51, 51, 51, 53, 53, 53, 53, 51, 52, 53, 53, 51, 59, 60, 60, 59, 60, 60, 59, 51, 51, 51, 51,
		51, 61, 62, 61, 62, 45, 63, 61, 62, 0, 0, 64, 62, 62, 62, 65, 61, 0, 0, 0, 0, 63, 47, 61, 65, 61,
		61, 61, 0, 61, 0, 61, 61, 62, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66, 66,
		0, 66, 66, 66, 66, 66, 66, 66, 61, 61, 62, 62, 62, 62, 62, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 62, 67, 67, 67, 67, 67, 67, 67, 62, 62, 62, 62, 62, 61, 62, 62, 61,
		61, 61, 62, 62, 62, 61, 62, 61, 62, 61, 62, 61, 62, 61, 62, 68, 69, 68, 69, 68, 69, 68, 69, 68,
		69, 68, 69, 68, 69, 62, 62, 62, 62, 61, 62, 70, 61, 62, 61, 61, 62, 62, 61, 61, 61, 71, 72, 71,
		71, 71, 71, 71, 71, 71, 71, 71, 71, 71, 71, 71, 71, 72, 72, 72, 72, 72, 72, 72, 72, 73, 73, 73,
		73, 73, 73, 73, 73, 74, 73, 74, 74, 74, 74, 74, 74, 74, 74, 74, 74, 74, 74, 74, 74, 71, 74, 71,
		74, 71, 74, 71, 74, 71, 74, 75, 76, 76, 77, 77, 76, 78, 78, 71, 74, 71, 74, 71, 74, 71, 71, 74,
		71, 74, 71, 74, 71, 74, 71, 74, 71, 74, 71, 74, 74, 0, 79, 79, 79, 79, 79, 79, 79, 79, 79, 79, 79,
		79, 79, 79, 79, 79, 79, 79, 79, 79, 79, 79, 0, 0, 80, 81, 81, 81, 81, 81, 81, 82, 82, 82, 82, 82,
		82, 82, 82, 82, 81, 83, 0, 0, 84, 84, 85, 86, 87, 88, 88, 88, 88, 87, 88, 88, 88, 89, 87, 88, 88,
		88, 88, 88, 88, 87, 87, 87, 87, 87, 87, 88, 88, 87, 88, 88, 89, 90, 88, 91, 92, 93, 94, 95, 96,
		97, 98, 99, 100, 100, 101, 102, 103, 104, 105, 106, 107, 108, 106, 88, 87, 106, 99, 86, 86, 86,
		86, 86, 86, 86, 86, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 86, 86, 86, 86, 109,
		109, 109, 109, 106, 106, 86, 86, 86, 110, 110, 110, 110, 110, 111, 112, 112, 113, 114, 114, 115,
		116, 117, 118, 118, 119, 119, 119, 119, 119, 119, 119, 119, 120, 121, 122, 123, 124, 117, 117,
		123, 125, 125, 125, 125, 125, 125, 125, 125, 126, 125, 125, 125, 125, 125, 125, 125, 125, 125,
		125, 127, 128, 129, 130, 131, 132, 133, 134, 77, 77, 135, 136, 119, 119, 119, 119, 119, 136, 119,
		119, 136, 137, 137, 137, 137, 137, 137, 137, 137, 137, 137, 114, 138, 138, 117, 125, 125, 139,
		125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 117, 125, 119, 119, 119, 119, 119, 119,
		119, 111, 118, 119, 119, 119, 119, 136, 119, 140, 140, 119, 119, 118, 136, 119, 119, 136, 125,
		125, 141, 141, 141, 141, 141, 141, 141, 141, 141, 141, 125, 125, 125, 142, 142, 125, 143, 143,
		143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 144, 145, 146, 147, 146, 146, 146,
		146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 148, 149, 148, 148, 149, 148, 148, 149,
		149, 149, 148, 149, 149, 148, 149, 148, 148, 148, 149, 148, 149, 148, 149, 148, 149, 148, 148,
		144, 144, 146, 146, 146, 150, 150, 150, 150, 150, 150, 150, 150, 150, 150, 150, 150, 150, 150,
		151, 151, 151, 151, 151, 151, 151, 151, 151, 151, 151, 150, 144, 144, 144, 144, 144, 144, 144,
		144, 144, 144, 144, 144, 144, 144, 152, 152, 152, 152, 152, 152, 152, 152, 152, 152, 153, 153,
		153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 154, 154, 154, 154,
		154, 154, 154, 155, 154, 156, 156, 157, 158, 158, 158, 156, 86, 86, 155, 159, 159, 160, 160, 160,
		160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 161, 161, 161, 161, 162, 161, 161, 161,
		161, 161, 161, 161, 161, 161, 162, 161, 161, 161, 162, 161, 161, 161, 161, 161, 86, 86, 163, 163,
		163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 86, 164, 164, 164, 164, 164, 164,
		164, 164, 164, 165, 165, 165, 86, 86, 166, 86, 146, 146, 146, 144, 144, 144, 144, 144, 167, 125,
		125, 125, 125, 125, 125, 125, 110, 110, 144, 144, 144, 144, 144, 119, 119, 136, 136, 136, 119,
		119, 119, 119, 125, 140, 119, 119, 119, 119, 119, 136, 136, 136, 136, 136, 119, 119, 119, 119,
		119, 119, 111, 136, 119, 119, 136, 119, 119, 136, 119, 119, 119, 136, 136, 136, 168, 169, 170,
		119, 119, 119, 136, 119, 119, 136, 136, 119, 119, 119, 119, 119, 171, 171, 171, 172, 173, 173,
		173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 171, 172, 174, 173, 172, 172, 172,
		171, 171, 171, 171, 171, 171, 171, 171, 172, 172, 172, 172, 175, 172, 172, 173, 77, 135, 77, 77,
		171, 171, 171, 173, 173, 171, 171, 176, 176, 177, 177, 177, 177, 177, 177, 177, 177, 177, 177,
		178, 179, 173, 173, 173, 173, 173, 173, 180, 181, 182, 182, 0, 180, 180, 180, 180, 180, 180, 180,
		180, 0, 0, 180, 180, 0, 0, 180, 180, 180, 180, 180, 180, 180, 180, 180, 180, 180, 180, 180, 180,
		0, 180, 180, 180, 180, 180, 180, 180, 0, 180, 0, 0, 0, 180, 180, 180, 180, 0, 0, 183, 180, 182,
		182, 182, 181, 181, 181, 181, 0, 0, 182, 182, 0, 0, 182, 182, 184, 180, 0, 0, 0, 0, 0, 0, 0, 0,
		182, 0, 0, 0, 0, 180, 180, 0, 180, 180, 180, 181, 181, 0, 0, 185, 185, 185, 185, 185, 185, 185,
		185, 185, 185, 180, 180, 186, 186, 187, 187, 187, 187, 187, 187, 188, 186, 180, 189, 190, 0, 0,
		191, 191, 192, 0, 193, 193, 193, 193, 193, 193, 0, 0, 0, 0, 193, 193, 0, 0, 193, 193, 193, 193,
		193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 0, 193, 193, 193, 193, 193, 193, 193, 0, 193,
		193, 0, 193, 193, 0, 193, 193, 0, 0, 194, 0, 192, 192, 192, 191, 191, 0, 0, 0, 0, 191, 191, 0, 0,
		191, 191, 195, 0, 0, 0, 191, 0, 0, 0, 0, 0, 0, 0, 193, 193, 193, 193, 0, 193, 0, 0, 0, 0, 0, 0, 0,
		196, 196, 196, 196, 196, 196, 196, 196, 196, 196, 191, 191, 193, 193, 193, 191, 197, 0, 0, 0, 0,
		0, 0, 0, 0, 0, 0, 198, 198, 199, 0, 200, 200, 200, 200, 200, 200, 200, 200, 200, 0, 200, 200, 200,
		0, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 0, 200, 200, 200, 200,
		200, 200, 200, 0, 200, 200, 0, 200, 200, 200, 200, 200, 0, 0, 201, 200, 199, 199, 199, 198, 198,
		198, 198, 198, 0, 198, 198, 199, 0, 199, 199, 202, 0, 0, 200, 0, 0, 0, 0, 0, 0, 0, 200, 200, 198,
		198, 0, 0, 203, 203, 203, 203, 203, 203, 203, 203, 203, 203, 204, 205, 0, 0, 0, 0, 0, 0, 0, 200,
		198, 198, 198, 198, 198, 198, 0, 206, 207, 207, 0, 208, 208, 208, 208, 208, 208, 208, 208, 0, 0,
		208, 208, 0, 0, 208, 208, 208, 208, 208, 208, 208, 208, 208, 208, 208, 208, 208, 208, 0, 208, 208,
		208, 208, 208, 208, 208, 0, 208, 208, 0, 208, 208, 208, 208, 208, 0, 0, 209, 208, 207, 206, 207,
		206, 206, 206, 206, 0, 0, 207, 207, 0, 0, 207, 207, 210, 0, 0, 0, 0, 0, 0, 0, 206, 206, 207, 0, 0,
		0, 0, 208, 208, 0, 208, 208, 208, 206, 206, 0, 0, 211, 211, 211, 211, 211, 211, 211, 211, 211,
		211, 212, 208, 213, 213, 213, 213, 213, 213, 0, 0, 214, 215, 0, 215, 215, 215, 215, 215, 215, 0,
		0, 0, 215, 215, 215, 0, 215, 215, 215, 215, 0, 0, 0, 215, 215, 0, 215, 0, 215, 215, 0, 0, 0, 215,
		215, 0, 0, 0, 215, 215, 215, 215, 215, 215, 215, 215, 215, 215, 0, 0, 0, 0, 216, 216, 214, 216,
		216, 0, 0, 0, 216, 216, 216, 0, 216, 216, 216, 217, 0, 0, 215, 0, 0, 0, 0, 0, 0, 216, 0, 0, 0, 0,
		0, 0, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 219, 219, 219, 220, 220, 220, 220, 220,
		220, 221, 220, 0, 0, 0, 0, 0, 222, 223, 223, 223, 222, 224, 224, 224, 224, 224, 224, 224, 224, 0,
		224, 224, 224, 0, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224, 224,
		0, 0, 225, 224, 222, 222, 222, 223, 223, 223, 223, 0, 222, 222, 222, 0, 222, 222, 222, 226, 0, 0,
		0, 0, 0, 0, 0, 227, 228, 0, 224, 224, 224, 0, 224, 224, 0, 0, 224, 224, 222, 222, 0, 0, 229, 229,
		229, 229, 229, 229, 229, 229, 229, 229, 0, 0, 0, 0, 0, 0, 0, 230, 231, 231, 231, 231, 231, 231,
		231, 232, 233, 234, 235, 235, 236, 233, 233, 233, 233, 233, 233, 233, 233, 0, 233, 233, 233, 0,
		233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 0, 233,
		233, 233, 233, 233, 0, 0, 237, 233, 235, 238, 235, 235, 235, 235, 235, 0, 238, 235, 235, 0, 235,
		235, 234, 239, 0, 0, 0, 0, 0, 0, 0, 235, 235, 0, 0, 0, 0, 0, 233, 233, 233, 0, 233, 233, 234, 234,
		0, 0, 240, 240, 240, 240, 240, 240, 240, 240, 240, 240, 0, 233, 233, 235, 0, 0, 0, 0, 241, 241,
		242, 242, 243, 243, 243, 243, 243, 243, 243, 243, 243, 0, 243, 243, 243, 0, 243, 243, 243, 243,
		243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 244, 244, 243, 242, 242, 242,
		241, 241, 241, 241, 0, 242, 242, 242, 0, 242, 242, 242, 244, 243, 245, 0, 0, 0, 0, 243, 243, 243,
		242, 246, 246, 246, 246, 246, 246, 246, 243, 243, 243, 241, 241, 0, 0, 247, 247, 247, 247, 247,
		247, 247, 247, 247, 247, 246, 246, 246, 246, 246, 246, 246, 246, 246, 245, 243, 243, 243, 243,
		243, 243, 0, 248, 249, 249, 0, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250,
		250, 250, 250, 250, 250, 0, 0, 0, 250, 250, 250, 250, 250, 250, 250, 250, 0, 250, 250, 250, 250,
		250, 250, 250, 250, 250, 0, 250, 0, 0, 0, 0, 251, 0, 0, 0, 0, 249, 249, 249, 248, 248, 248, 0,
		248, 0, 249, 249, 249, 249, 249, 249, 249, 249, 0, 0, 0, 0, 0, 0, 252, 252, 252, 252, 252, 252,
		252, 252, 252, 252, 0, 0, 249, 249, 253, 0, 0, 0, 0, 254, 254, 254, 254, 254, 254, 254, 254, 254,
		254, 254, 254, 254, 254, 254, 254, 255, 254, 254, 255, 255, 255, 255, 256, 256, 257, 0, 0, 0, 0,
		258, 254, 254, 254, 254, 254, 254, 259, 255, 260, 260, 260, 260, 255, 255, 255, 261, 262, 262,
		262, 262, 262, 262, 262, 262, 262, 262, 261, 261, 0, 0, 0, 0, 0, 263, 263, 0, 263, 0, 263, 263,
		263, 263, 263, 0, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263,
		0, 263, 0, 263, 263, 264, 263, 263, 264, 264, 264, 264, 265, 265, 266, 264, 264, 263, 0, 0, 263,
		263, 263, 263, 263, 0, 267, 0, 268, 268, 268, 268, 264, 264, 264, 0, 269, 269, 269, 269, 269, 269,
		269, 269, 269, 269, 0, 0, 263, 263, 263, 263, 270, 271, 271, 271, 272, 272, 272, 272, 272, 272,
		272, 272, 272, 272, 272, 272, 272, 272, 272, 271, 272, 271, 271, 271, 273, 273, 271, 271, 271,
		271, 271, 271, 274, 274, 274, 274, 274, 274, 274, 274, 274, 274, 275, 275, 275, 275, 275, 275,
		275, 275, 275, 275, 271, 273, 271, 273, 271, 276, 277, 278, 277, 278, 279, 279, 270, 270, 270,
		270, 270, 270, 270, 270, 0, 270, 270, 270, 270, 270, 270, 270, 270, 270, 270, 270, 270, 0, 0, 0,
		0, 280, 281, 282, 283, 282, 282, 282, 282, 282, 281, 281, 281, 281, 282, 279, 281, 282, 284, 284,
		285, 272, 284, 284, 270, 270, 270, 270, 270, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282,
		282, 0, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282, 0, 271, 271, 271, 271, 271,
		271, 271, 271, 273, 271, 271, 271, 271, 271, 271, 0, 271, 271, 272, 272, 272, 272, 272, 286, 286,
		286, 286, 272, 272, 0, 0, 0, 0, 0, 287, 287, 287, 287, 287, 287, 287, 287, 287, 287, 287, 288,
		288, 289, 289, 289, 289, 288, 289, 289, 289, 289, 289, 290, 288, 291, 291, 288, 288, 289, 289,
		287, 292, 292, 292, 292, 292, 292, 292, 292, 292, 292, 293, 293, 293, 293, 293, 293, 287, 287,
		287, 287, 287, 287, 288, 288, 289, 289, 287, 287, 287, 287, 289, 289, 289, 287, 288, 288, 288,
		287, 287, 288, 288, 288, 288, 288, 288, 288, 287, 287, 287, 289, 289, 289, 289, 287, 287, 287,
		287, 287, 289, 288, 288, 289, 289, 288, 288, 288, 288, 288, 288, 294, 287, 288, 292, 292, 288,
		288, 288, 289, 295, 295, 296, 296, 296, 296, 296, 296, 296, 296, 296, 296, 296, 296, 296, 296, 0,
		296, 0, 0, 0, 0, 0, 296, 0, 0, 297, 297, 297, 297, 297, 297, 297, 297, 297, 297, 297, 176, 298,
		297, 297, 297, 299, 299, 299, 299, 299, 299, 299, 299, 300, 300, 300, 300, 300, 300, 300, 300,
		301, 301, 301, 301, 301, 301, 301, 301, 301, 0, 301, 301, 301, 301, 0, 0, 301, 301, 301, 301, 301,
		301, 301, 0, 301, 301, 301, 0, 0, 302, 302, 302, 303, 303, 303, 303, 303, 303, 303, 303, 303, 304,
		304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 0,
		0, 0, 305, 305, 305, 305, 305, 305, 305, 305, 305, 305, 0, 0, 0, 0, 0, 0, 306, 306, 306, 306, 306,
		306, 306, 306, 306, 306, 306, 306, 306, 306, 0, 0, 307, 307, 307, 307, 307, 307, 0, 0, 308, 309,
		309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309,
		310, 311, 309, 312, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313,
		313, 313, 313, 314, 315, 0, 0, 0, 316, 316, 316, 316, 316, 316, 316, 316, 316, 316, 316, 176, 176,
		176, 317, 317, 317, 316, 316, 316, 316, 316, 316, 316, 316, 0, 0, 0, 0, 0, 0, 0, 318, 318, 318,
		318, 318, 318, 318, 318, 318, 318, 319, 319, 320, 321, 0, 0, 0, 0, 0, 0, 0, 0, 0, 318, 322, 322,
		322, 322, 322, 322, 322, 322, 322, 322, 323, 323, 324, 176, 176, 0, 325, 325, 325, 325, 325, 325,
		325, 325, 325, 325, 326, 326, 0, 0, 0, 0, 327, 327, 327, 327, 327, 327, 327, 327, 327, 327, 327,
		327, 327, 0, 327, 327, 327, 0, 328, 328, 0, 0, 0, 0, 329, 329, 329, 329, 329, 329, 329, 329, 329,
		329, 329, 329, 330, 330, 331, 330, 330, 330, 330, 330, 330, 330, 331, 331, 331, 331, 331, 331,
		331, 331, 330, 331, 331, 330, 330, 330, 330, 330, 330, 330, 330, 330, 332, 330, 333, 333, 333,
		334, 333, 333, 333, 335, 329, 336, 0, 0, 337, 337, 337, 337, 337, 337, 337, 337, 337, 337, 0, 0,
		0, 0, 0, 0, 338, 338, 338, 338, 338, 338, 338, 338, 338, 338, 0, 0, 0, 0, 0, 0, 339, 339, 65, 65,
		339, 65, 340, 339, 339, 339, 339, 341, 341, 341, 342, 341, 343, 343, 343, 343, 343, 343, 343, 343,
		343, 343, 0, 0, 0, 0, 0, 0, 344, 344, 344, 344, 344, 344, 344, 344, 344, 344, 344, 345, 344, 344,
		344, 344, 344, 0, 0, 0, 0, 0, 0, 0, 344, 344, 344, 344, 344, 341, 341, 344, 344, 346, 344, 0, 0,
		0, 0, 0, 309, 309, 309, 309, 309, 309, 0, 0, 347, 347, 347, 347, 347, 347, 347, 347, 347, 347,
		347, 347, 347, 347, 347, 0, 348, 348, 348, 349, 349, 349, 349, 348, 348, 349, 349, 349, 0, 0, 0,
		0, 349, 349, 348, 349, 349, 349, 349, 349, 349, 350, 351, 352, 0, 0, 0, 0, 353, 0, 0, 0, 354, 354,
		355, 355, 355, 355, 355, 355, 355, 355, 355, 355, 356, 356, 356, 356, 356, 356, 356, 356, 356,
		356, 356, 356, 356, 356, 0, 0, 356, 356, 356, 356, 356, 0, 0, 0, 357, 357, 357, 357, 357, 357,
		357, 357, 357, 357, 357, 357, 0, 0, 0, 0, 357, 357, 0, 0, 0, 0, 0, 0, 358, 358, 358, 358, 358,
		358, 358, 358, 358, 358, 359, 0, 0, 0, 360, 360, 361, 361, 361, 361, 361, 361, 361, 361, 362, 362,
		362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 363, 364, 365, 365, 366, 0, 0,
		367, 367, 368, 368, 368, 368, 368, 368, 368, 368, 368, 368, 368, 368, 368, 369, 370, 369, 370,
		370, 370, 370, 370, 370, 370, 0, 371, 369, 370, 369, 369, 370, 370, 370, 370, 370, 370, 370, 370,
		369, 369, 369, 369, 369, 369, 370, 370, 372, 372, 372, 372, 372, 372, 372, 372, 0, 0, 373, 374,
		374, 374, 374, 374, 374, 374, 374, 374, 374, 0, 0, 0, 0, 0, 0, 375, 375, 375, 375, 375, 375, 375,
		376, 375, 375, 375, 375, 375, 375, 0, 0, 77, 77, 77, 77, 77, 135, 135, 135, 135, 135, 135, 77, 77,
		135, 377, 135, 135, 77, 77, 135, 135, 77, 77, 77, 77, 77, 135, 77, 77, 77, 77, 77, 77, 77, 77, 77,
		77, 77, 77, 77, 77, 77, 77, 77, 77, 135, 0, 0, 77, 77, 77, 77, 77, 77, 135, 77, 77, 77, 77, 378,
		0, 0, 0, 0, 379, 379, 379, 379, 380, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381,
		381, 381, 381, 382, 380, 379, 379, 379, 379, 379, 380, 379, 380, 380, 380, 380, 380, 379, 380,
		383, 381, 381, 381, 381, 381, 381, 381, 381, 0, 384, 384, 385, 385, 385, 385, 385, 385, 385, 385,
		385, 385, 384, 384, 384, 384, 384, 384, 384, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386,
		387, 388, 387, 387, 387, 387, 387, 387, 387, 386, 386, 386, 386, 386, 386, 386, 386, 386, 384,
		384, 384, 389, 389, 390, 391, 391, 391, 391, 391, 391, 391, 391, 391, 391, 391, 391, 391, 391,
		390, 389, 389, 389, 389, 390, 390, 389, 389, 392, 393, 389, 389, 391, 391, 394, 394, 394, 394,
		394, 394, 394, 394, 394, 394, 391, 391, 391, 391, 391, 391, 395, 395, 395, 395, 395, 395, 395,
		395, 395, 395, 395, 395, 395, 395, 396, 397, 398, 398, 397, 397, 397, 398, 397, 398, 398, 398,
		399, 399, 0, 0, 0, 0, 0, 0, 0, 0, 400, 400, 400, 400, 401, 401, 401, 401, 401, 401, 401, 401, 401,
		401, 401, 401, 402, 402, 402, 402, 402, 402, 402, 402, 403, 403, 403, 403, 403, 403, 403, 403,
		402, 402, 403, 404, 0, 0, 0, 405, 405, 405, 405, 405, 406, 406, 406, 406, 406, 406, 406, 406, 406,
		406, 0, 0, 0, 401, 401, 401, 407, 407, 407, 407, 407, 407, 407, 407, 407, 407, 408, 408, 408, 408,
		408, 408, 408, 408, 408, 408, 408, 408, 408, 408, 409, 409, 409, 409, 409, 409, 410, 410, 74, 71,
		74, 0, 0, 0, 0, 0, 296, 296, 296, 0, 0, 296, 296, 296, 411, 411, 411, 411, 411, 411, 411, 411, 77,
		77, 77, 176, 412, 135, 135, 135, 135, 135, 77, 77, 135, 135, 135, 135, 77, 413, 412, 412, 412,
		412, 412, 412, 412, 414, 414, 414, 414, 135, 414, 414, 414, 414, 414, 414, 77, 414, 414, 413, 77,
		77, 414, 0, 0, 0, 0, 0, 41, 41, 41, 41, 41, 41, 62, 62, 62, 62, 62, 74, 44, 44, 44, 44, 44, 44,
		44, 44, 44, 64, 64, 64, 64, 64, 44, 44, 44, 44, 64, 64, 64, 64, 64, 41, 41, 41, 41, 41, 415, 41,
		41, 41, 41, 41, 41, 41, 41, 41, 41, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 64, 77, 77,
		135, 77, 77, 378, 416, 135, 417, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 418, 419,
		419, 135, 420, 77, 421, 135, 77, 135, 37, 41, 37, 41, 37, 41, 41, 41, 41, 41, 41, 41, 41, 41, 37,
		41, 62, 62, 62, 62, 62, 62, 62, 62, 61, 61, 61, 61, 61, 61, 61, 61, 62, 62, 62, 62, 62, 62, 0, 0,
		61, 61, 61, 61, 61, 61, 0, 0, 0, 61, 0, 61, 0, 61, 0, 61, 422, 422, 422, 422, 422, 422, 422, 422,
		62, 62, 62, 62, 62, 0, 62, 62, 61, 61, 61, 61, 422, 63, 62, 63, 63, 63, 62, 62, 62, 0, 62, 62, 61,
		61, 61, 61, 422, 63, 63, 63, 62, 62, 62, 62, 0, 0, 62, 62, 61, 61, 61, 61, 0, 63, 63, 63, 61, 61,
		61, 61, 61, 63, 63, 63, 0, 0, 62, 62, 62, 0, 62, 62, 61, 61, 61, 61, 422, 63, 63, 0, 423, 423,
		423, 423, 423, 423, 423, 423, 423, 423, 423, 424, 425, 425, 426, 427, 428, 429, 429, 428, 428,
		428, 22, 65, 430, 431, 432, 433, 430, 431, 432, 433, 22, 22, 22, 65, 22, 22, 22, 22, 434, 435,
		436, 437, 438, 439, 440, 21, 441, 442, 441, 441, 442, 22, 65, 65, 65, 28, 35, 22, 65, 65, 22, 443,
		443, 65, 65, 65, 444, 445, 446, 65, 65, 65, 65, 65, 65, 65, 65, 65, 65, 65, 447, 65, 443, 65, 65,
		65, 65, 65, 65, 65, 65, 65, 65, 423, 424, 424, 424, 424, 424, 448, 449, 449, 449, 449, 424, 424,
		424, 424, 424, 424, 450, 44, 0, 0, 33, 450, 450, 450, 450, 450, 451, 451, 447, 445, 446, 452, 450,
		33, 33, 33, 33, 450, 450, 450, 450, 450, 451, 451, 447, 445, 446, 0, 44, 44, 44, 44, 44, 0, 0, 0,
		258, 258, 258, 258, 258, 258, 258, 258, 258, 453, 258, 258, 23, 258, 258, 258, 258, 258, 454, 454,
		454, 454, 454, 454, 454, 454, 454, 454, 454, 454, 454, 454, 77, 77, 412, 412, 77, 77, 77, 77, 412,
		412, 412, 77, 77, 377, 377, 377, 377, 77, 377, 377, 377, 412, 412, 77, 135, 77, 412, 412, 135,
		135, 135, 135, 77, 0, 0, 0, 0, 0, 0, 0, 26, 26, 455, 30, 26, 30, 26, 455, 26, 30, 34, 455, 455,
		455, 34, 34, 455, 455, 455, 456, 26, 455, 30, 26, 447, 455, 455, 455, 455, 455, 26, 26, 26, 30,
		30, 26, 455, 26, 66, 26, 455, 26, 37, 38, 455, 455, 457, 34, 455, 455, 37, 455, 34, 414, 414, 414,
		414, 34, 26, 26, 34, 34, 455, 455, 458, 447, 447, 447, 447, 455, 34, 34, 34, 34, 26, 447, 26, 26,
		41, 286, 459, 459, 459, 36, 36, 459, 459, 459, 459, 459, 459, 36, 36, 36, 36, 459, 460, 460, 460,
		460, 460, 460, 460, 460, 460, 460, 460, 460, 461, 461, 461, 461, 460, 460, 461, 461, 461, 461,
		461, 461, 461, 461, 461, 37, 41, 461, 461, 461, 461, 36, 26, 26, 0, 0, 0, 0, 39, 39, 39, 39, 39,
		30, 30, 30, 30, 30, 447, 447, 26, 26, 26, 26, 447, 26, 26, 447, 26, 26, 447, 26, 26, 26, 26, 26,
		26, 26, 447, 26, 26, 26, 26, 26, 26, 26, 26, 26, 30, 30, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26,
		26, 26, 447, 447, 26, 26, 39, 26, 39, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 30, 26, 26, 26, 26,
		447, 447, 447, 447, 447, 447, 447, 447, 447, 447, 447, 447, 39, 458, 462, 462, 458, 447, 447, 39,
		462, 458, 458, 462, 458, 458, 447, 39, 447, 462, 451, 463, 447, 462, 458, 447, 447, 447, 462, 458,
		458, 462, 39, 462, 462, 458, 458, 39, 458, 39, 458, 39, 39, 39, 39, 462, 462, 458, 462, 458, 458,
		458, 458, 458, 39, 39, 39, 39, 447, 458, 447, 458, 462, 462, 458, 458, 458, 458, 458, 458, 458,
		458, 458, 458, 462, 458, 458, 458, 462, 447, 447, 447, 447, 447, 462, 458, 458, 458, 447, 447,
		447, 447, 447, 447, 447, 447, 447, 458, 462, 39, 458, 447, 462, 462, 462, 462, 458, 458, 462, 462,
		447, 458, 462, 462, 458, 458, 462, 462, 458, 458, 462, 462, 458, 458, 458, 458, 458, 447, 447,
		458, 458, 458, 458, 447, 447, 39, 447, 447, 458, 39, 447, 447, 447, 447, 447, 447, 447, 447, 458,
		458, 447, 39, 458, 458, 458, 447, 447, 447, 447, 447, 458, 462, 447, 458, 458, 458, 458, 458, 447,
		447, 458, 458, 447, 447, 447, 447, 458, 458, 458, 458, 458, 458, 458, 458, 447, 447, 445, 446,
		445, 446, 26, 26, 26, 26, 26, 26, 30, 26, 26, 26, 26, 26, 26, 26, 464, 464, 26, 26, 26, 26, 458,
		458, 26, 26, 26, 26, 26, 26, 26, 465, 466, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 286, 286,
		286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 26, 447, 26, 26, 26, 26, 26, 26, 26, 26,
		286, 26, 26, 26, 26, 26, 447, 447, 447, 447, 447, 447, 447, 447, 447, 26, 26, 26, 26, 447, 447,
		26, 26, 26, 26, 26, 26, 26, 464, 464, 464, 464, 26, 26, 26, 464, 26, 26, 464, 26, 26, 26, 26, 26,
		26, 0, 0, 0, 0, 0, 0, 26, 26, 26, 0, 0, 0, 0, 0, 36, 36, 36, 36, 36, 36, 36, 36, 33, 33, 33, 33,
		33, 33, 33, 33, 33, 33, 33, 33, 467, 467, 467, 467, 467, 467, 467, 467, 467, 467, 467, 467, 467,
		467, 459, 36, 36, 36, 36, 36, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 26, 26, 26, 26, 26,
		26, 30, 30, 30, 30, 26, 26, 30, 30, 26, 30, 30, 30, 30, 30, 26, 26, 30, 30, 26, 26, 30, 39, 26,
		26, 26, 26, 30, 30, 26, 26, 30, 39, 26, 26, 26, 26, 30, 30, 30, 26, 26, 30, 26, 26, 30, 30, 447,
		447, 447, 447, 447, 468, 468, 447, 26, 26, 26, 26, 26, 30, 30, 26, 26, 30, 26, 26, 26, 26, 30, 30,
		26, 26, 26, 26, 464, 464, 26, 26, 26, 26, 26, 26, 30, 26, 30, 26, 464, 464, 464, 464, 464, 464,
		464, 464, 30, 26, 30, 26, 26, 26, 26, 26, 464, 464, 464, 464, 26, 26, 26, 26, 30, 30, 26, 30, 30,
		30, 26, 30, 30, 30, 30, 26, 30, 30, 26, 39, 26, 26, 26, 26, 26, 26, 26, 464, 26, 26, 464, 464,
		464, 464, 464, 464, 26, 26, 26, 464, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 30, 30, 26, 464, 26,
		26, 26, 26, 26, 26, 26, 26, 464, 464, 286, 26, 26, 26, 26, 26, 26, 26, 26, 464, 464, 30, 26, 26,
		26, 26, 464, 464, 30, 30, 30, 30, 30, 30, 30, 30, 464, 30, 30, 30, 30, 30, 464, 30, 30, 30, 30,
		30, 26, 30, 26, 26, 26, 26, 30, 30, 464, 30, 30, 30, 30, 30, 30, 30, 464, 464, 30, 464, 30, 30,
		30, 30, 464, 30, 30, 464, 30, 30, 26, 26, 26, 26, 26, 464, 26, 26, 464, 26, 26, 26, 26, 26, 26,
		26, 26, 26, 26, 26, 26, 30, 26, 26, 26, 26, 26, 26, 464, 26, 464, 26, 26, 26, 26, 464, 464, 464,
		26, 464, 445, 446, 445, 446, 445, 446, 445, 446, 445, 446, 445, 446, 445, 446, 36, 36, 459, 459,
		459, 459, 459, 459, 459, 459, 459, 459, 459, 459, 26, 464, 464, 464, 458, 447, 447, 458, 458, 445,
		446, 447, 458, 458, 447, 458, 458, 458, 447, 447, 447, 447, 447, 458, 458, 458, 458, 447, 447,
		447, 447, 447, 458, 458, 458, 447, 447, 447, 458, 458, 458, 458, 9, 10, 9, 10, 9, 10, 9, 10, 445,
		446, 469, 469, 469, 469, 469, 469, 469, 469, 447, 447, 447, 445, 446, 9, 10, 445, 446, 445, 446,
		445, 446, 445, 446, 445, 446, 447, 447, 458, 458, 458, 458, 458, 458, 447, 458, 458, 458, 458,
		458, 458, 458, 447, 447, 447, 447, 447, 447, 447, 447, 458, 447, 447, 447, 447, 458, 458, 458,
		458, 458, 447, 458, 458, 447, 447, 445, 446, 445, 446, 458, 447, 447, 447, 447, 458, 447, 458,
		458, 458, 447, 447, 458, 458, 447, 447, 447, 447, 447, 447, 447, 447, 447, 447, 458, 458, 458,
		458, 458, 458, 447, 447, 445, 446, 447, 447, 447, 447, 458, 458, 458, 458, 458, 458, 458, 458,
		458, 458, 458, 447, 458, 458, 458, 458, 447, 447, 458, 447, 458, 447, 447, 458, 447, 458, 458,
		458, 458, 447, 447, 447, 447, 447, 458, 458, 447, 447, 447, 447, 458, 458, 458, 458, 447, 458,
		458, 447, 447, 458, 458, 447, 447, 447, 447, 458, 458, 458, 458, 458, 458, 458, 458, 458, 458,
		458, 447, 447, 458, 458, 458, 458, 458, 458, 458, 458, 447, 458, 458, 458, 458, 458, 458, 458,
		458, 447, 447, 447, 447, 447, 458, 447, 458, 447, 447, 447, 458, 458, 458, 458, 458, 447, 447,
		447, 447, 458, 447, 447, 447, 458, 458, 458, 458, 458, 447, 458, 447, 447, 26, 26, 26, 464, 464,
		26, 26, 26, 447, 447, 447, 447, 447, 26, 26, 447, 447, 447, 447, 447, 447, 26, 26, 26, 464, 26,
		26, 26, 26, 464, 30, 30, 26, 26, 26, 26, 0, 0, 26, 26, 26, 26, 26, 26, 26, 26, 470, 26, 471, 471,
		471, 471, 471, 471, 471, 471, 472, 472, 472, 472, 472, 472, 472, 472, 37, 41, 37, 37, 37, 41, 41,
		37, 41, 37, 41, 37, 41, 37, 37, 37, 37, 41, 37, 41, 41, 37, 41, 41, 41, 41, 41, 41, 44, 44, 37,
		37, 68, 69, 68, 69, 69, 473, 473, 473, 473, 473, 473, 68, 69, 68, 69, 474, 474, 474, 68, 69, 0, 0,
		0, 0, 0, 475, 475, 475, 475, 476, 475, 475, 297, 297, 297, 297, 297, 297, 0, 297, 0, 0, 0, 0, 0,
		297, 0, 0, 477, 477, 477, 477, 477, 477, 477, 477, 0, 0, 0, 0, 0, 0, 0, 478, 479, 0, 0, 0, 0, 0,
		0, 0, 0, 0, 0, 0, 0, 0, 0, 480, 76, 76, 76, 76, 76, 76, 76, 76, 65, 65, 28, 35, 28, 35, 65, 65,
		65, 28, 35, 65, 28, 35, 65, 65, 65, 65, 65, 65, 65, 65, 65, 429, 65, 65, 429, 65, 28, 35, 65, 65,
		28, 35, 445, 446, 445, 446, 445, 446, 445, 446, 65, 65, 65, 65, 65, 45, 65, 65, 429, 429, 65, 65,
		65, 65, 429, 65, 432, 65, 65, 65, 65, 65, 26, 26, 65, 65, 65, 445, 446, 445, 446, 445, 446, 445,
		446, 429, 0, 0, 481, 481, 481, 481, 481, 481, 481, 481, 481, 481, 0, 481, 481, 481, 481, 481, 481,
		481, 481, 481, 0, 0, 0, 0, 481, 481, 481, 481, 481, 481, 0, 0, 482, 483, 483, 483, 464, 484, 485,
		486, 465, 466, 465, 466, 465, 466, 465, 466, 465, 466, 464, 464, 465, 466, 465, 466, 465, 466,
		465, 466, 487, 488, 489, 489, 464, 486, 486, 486, 486, 486, 486, 486, 486, 486, 490, 491, 492,
		493, 494, 494, 487, 495, 495, 495, 495, 495, 464, 464, 486, 486, 486, 484, 485, 483, 464, 26, 0,
		496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496, 496,
		496, 496, 496, 0, 0, 497, 497, 498, 498, 499, 499, 496, 487, 500, 500, 500, 500, 500, 500, 500,
		500, 500, 500, 500, 500, 500, 500, 500, 500, 500, 500, 483, 495, 501, 501, 500, 0, 0, 0, 0, 0,
		502, 502, 502, 502, 502, 502, 502, 502, 502, 502, 502, 0, 299, 299, 299, 299, 299, 299, 299, 299,
		299, 299, 299, 299, 299, 299, 0, 503, 503, 504, 504, 504, 504, 503, 503, 503, 503, 503, 503, 503,
		503, 503, 503, 464, 464, 464, 464, 464, 464, 0, 0, 0, 0, 0, 0, 0, 0, 0, 464, 505, 505, 505, 505,
		505, 505, 505, 505, 505, 505, 505, 505, 505, 506, 506, 0, 504, 504, 504, 504, 504, 504, 504, 504,
		504, 504, 503, 503, 503, 503, 503, 503, 507, 507, 507, 507, 507, 507, 507, 507, 464, 508, 508,
		508, 508, 508, 508, 508, 508, 508, 508, 508, 508, 508, 508, 508, 505, 505, 505, 505, 506, 506,
		506, 503, 503, 508, 508, 508, 508, 508, 508, 508, 503, 503, 503, 503, 464, 464, 464, 464, 509,
		509, 509, 509, 509, 509, 509, 509, 509, 509, 509, 509, 509, 509, 509, 503, 503, 503, 503, 503,
		503, 503, 503, 464, 464, 464, 464, 503, 503, 503, 503, 503, 503, 503, 503, 503, 503, 503, 464,
		464, 510, 510, 510, 510, 510, 510, 510, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511,
		511, 511, 511, 512, 511, 511, 511, 511, 511, 511, 511, 0, 0, 0, 513, 513, 513, 513, 513, 513, 513,
		513, 513, 513, 513, 513, 513, 513, 513, 0, 514, 514, 514, 514, 514, 514, 514, 514, 515, 515, 515,
		515, 515, 515, 516, 516, 517, 517, 517, 517, 517, 517, 517, 517, 517, 517, 517, 517, 518, 519,
		519, 519, 520, 520, 520, 520, 520, 520, 520, 520, 520, 520, 517, 517, 0, 0, 0, 0, 71, 74, 71, 74,
		71, 74, 521, 76, 78, 78, 78, 522, 76, 76, 76, 76, 76, 76, 76, 76, 76, 76, 522, 523, 71, 74, 71,
		74, 415, 415, 76, 76, 524, 524, 524, 524, 524, 524, 524, 524, 524, 524, 524, 524, 524, 524, 525,
		525, 525, 525, 525, 525, 525, 525, 525, 525, 526, 526, 527, 527, 527, 527, 527, 527, 47, 47, 47,
		47, 47, 47, 47, 45, 45, 45, 45, 45, 45, 45, 45, 45, 47, 47, 37, 41, 37, 41, 37, 41, 41, 41, 37,
		41, 37, 41, 37, 41, 44, 41, 41, 41, 41, 41, 41, 41, 41, 37, 41, 37, 41, 37, 37, 41, 45, 528, 528,
		37, 41, 37, 41, 42, 37, 41, 37, 41, 41, 41, 37, 41, 37, 41, 37, 37, 37, 37, 37, 41, 37, 37, 37,
		37, 37, 41, 37, 41, 37, 41, 37, 41, 37, 37, 37, 37, 41, 37, 41, 37, 37, 41, 37, 41, 37, 41, 37,
		41, 37, 0, 0, 0, 0, 44, 44, 44, 44, 37, 41, 42, 44, 44, 41, 42, 42, 42, 42, 42, 529, 529, 530,
		529, 529, 529, 531, 529, 529, 529, 529, 530, 529, 529, 529, 529, 529, 529, 529, 529, 529, 529,
		529, 529, 529, 529, 529, 532, 532, 530, 530, 532, 533, 533, 533, 533, 531, 0, 0, 0, 534, 534, 534,
		534, 534, 534, 286, 286, 258, 457, 0, 0, 0, 0, 0, 0, 535, 535, 535, 535, 535, 535, 535, 535, 535,
		535, 535, 535, 536, 536, 536, 536, 537, 537, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538,
		538, 538, 538, 538, 538, 538, 538, 538, 537, 537, 537, 537, 537, 537, 537, 537, 537, 537, 537,
		537, 537, 537, 537, 537, 539, 540, 0, 0, 0, 0, 0, 0, 0, 0, 541, 541, 542, 542, 542, 542, 542, 542,
		542, 542, 542, 542, 0, 0, 0, 0, 0, 0, 543, 543, 543, 543, 543, 543, 543, 543, 543, 543, 173, 173,
		173, 173, 173, 173, 178, 178, 178, 173, 178, 173, 173, 171, 544, 544, 544, 544, 544, 544, 544,
		544, 544, 544, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545, 545,
		545, 545, 545, 545, 546, 546, 546, 546, 546, 547, 547, 547, 176, 548, 549, 549, 549, 549, 549,
		549, 549, 549, 549, 549, 549, 549, 549, 549, 549, 550, 550, 550, 550, 550, 550, 550, 550, 550,
		550, 550, 551, 552, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 553, 299, 299, 299, 299, 299, 0, 0, 0, 554,
		554, 554, 555, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 557,
		555, 555, 554, 554, 554, 554, 555, 555, 554, 554, 555, 555, 558, 559, 559, 559, 559, 559, 559,
		559, 559, 559, 559, 559, 559, 559, 0, 46, 560, 560, 560, 560, 560, 560, 560, 560, 560, 560, 0, 0,
		0, 0, 559, 559, 287, 287, 287, 287, 287, 289, 561, 287, 292, 292, 287, 287, 287, 287, 287, 0, 562,
		562, 562, 562, 562, 562, 562, 562, 562, 563, 563, 563, 563, 563, 563, 564, 564, 563, 563, 564,
		564, 563, 563, 0, 562, 562, 562, 563, 562, 562, 562, 562, 562, 562, 562, 562, 563, 564, 0, 0, 565,
		565, 565, 565, 565, 565, 565, 565, 565, 565, 0, 0, 566, 566, 566, 566, 561, 287, 287, 287, 287,
		287, 287, 295, 295, 295, 287, 288, 289, 288, 287, 287, 567, 567, 567, 567, 567, 567, 567, 567,
		568, 567, 568, 568, 569, 567, 567, 568, 568, 567, 567, 567, 567, 567, 568, 568, 567, 568, 567, 0,
		0, 0, 0, 0, 0, 0, 0, 567, 567, 570, 571, 571, 572, 572, 572, 572, 572, 572, 572, 572, 572, 572,
		572, 573, 574, 574, 573, 573, 575, 575, 572, 576, 576, 573, 577, 0, 0, 301, 301, 301, 301, 301,
		301, 0, 41, 41, 41, 528, 44, 44, 44, 44, 41, 41, 41, 41, 41, 62, 41, 41, 41, 44, 47, 47, 0, 0, 0,
		0, 307, 307, 307, 307, 307, 307, 307, 307, 572, 572, 572, 573, 573, 574, 573, 573, 574, 573, 573,
		575, 573, 577, 0, 0, 578, 578, 578, 578, 578, 578, 578, 578, 578, 578, 0, 0, 0, 0, 0, 0, 299, 299,
		299, 299, 0, 0, 0, 0, 300, 300, 300, 300, 300, 300, 300, 0, 0, 0, 0, 300, 300, 300, 300, 300, 300,
		300, 300, 300, 0, 0, 0, 0, 579, 579, 579, 579, 579, 579, 579, 579, 580, 580, 580, 580, 580, 580,
		580, 580, 510, 510, 510, 510, 510, 510, 581, 581, 510, 510, 581, 581, 581, 581, 581, 581, 581,
		581, 581, 581, 581, 581, 581, 581, 41, 41, 41, 41, 41, 41, 41, 0, 0, 0, 0, 82, 82, 82, 82, 82, 0,
		0, 0, 0, 0, 109, 582, 109, 109, 583, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109,
		109, 86, 109, 109, 109, 109, 109, 86, 109, 86, 109, 109, 86, 109, 109, 86, 109, 109, 125, 125,
		167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 118, 118,
		118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 125, 125, 125, 125, 125,
		125, 125, 125, 125, 125, 125, 584, 432, 118, 118, 125, 125, 125, 125, 125, 125, 448, 448, 448,
		448, 448, 448, 448, 448, 125, 125, 125, 125, 115, 118, 118, 118, 58, 58, 58, 58, 58, 58, 58, 58,
		483, 483, 483, 483, 483, 483, 483, 488, 489, 483, 0, 0, 0, 0, 0, 0, 77, 77, 77, 77, 77, 77, 77,
		135, 135, 135, 135, 135, 135, 135, 76, 76, 483, 487, 487, 585, 585, 488, 489, 488, 489, 488, 489,
		488, 489, 488, 489, 488, 489, 488, 489, 488, 489, 483, 483, 488, 489, 483, 483, 483, 483, 585,
		585, 585, 586, 483, 586, 0, 483, 586, 483, 483, 487, 465, 466, 465, 466, 465, 466, 587, 483, 483,
		588, 589, 590, 590, 468, 0, 483, 591, 587, 483, 0, 0, 0, 0, 125, 125, 125, 125, 125, 144, 125,
		125, 125, 125, 125, 125, 125, 144, 144, 424, 0, 592, 592, 593, 594, 593, 592, 592, 595, 596, 592,
		597, 598, 599, 598, 598, 600, 600, 600, 600, 600, 600, 600, 600, 600, 600, 598, 592, 601, 602,
		601, 592, 592, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603, 603,
		603, 603, 595, 592, 596, 604, 605, 604, 606, 606, 606, 606, 606, 606, 606, 606, 606, 606, 606,
		606, 606, 606, 606, 606, 606, 606, 595, 602, 596, 602, 595, 596, 607, 608, 609, 607, 607, 610,
		610, 610, 610, 610, 610, 610, 610, 610, 610, 611, 610, 610, 610, 610, 610, 610, 610, 610, 610,
		610, 610, 610, 610, 611, 611, 612, 612, 612, 612, 612, 612, 612, 612, 612, 612, 612, 612, 612,
		612, 612, 0, 0, 0, 612, 612, 612, 612, 612, 612, 0, 0, 612, 612, 612, 0, 0, 0, 594, 594, 602, 604,
		613, 594, 594, 0, 614, 615, 615, 615, 615, 614, 614, 0, 448, 449, 449, 449, 26, 30, 448, 448, 616,
		616, 616, 616, 616, 616, 616, 616, 616, 616, 616, 616, 0, 616, 616, 616, 616, 616, 616, 616, 616,
		616, 616, 0, 616, 616, 616, 0, 616, 616, 0, 616, 616, 616, 616, 616, 616, 616, 0, 0, 616, 616,
		616, 0, 0, 0, 0, 0, 176, 65, 176, 0, 0, 0, 0, 534, 534, 534, 534, 534, 534, 534, 534, 534, 534,
		534, 534, 534, 0, 0, 0, 286, 617, 617, 617, 617, 617, 617, 617, 617, 617, 617, 617, 617, 617, 618,
		618, 618, 618, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619, 619,
		619, 618, 618, 619, 620, 620, 0, 26, 26, 26, 26, 26, 0, 0, 0, 619, 0, 0, 0, 0, 0, 0, 0, 286, 286,
		286, 286, 286, 135, 0, 0, 621, 621, 621, 621, 621, 621, 621, 621, 621, 621, 621, 621, 621, 0, 0,
		0, 622, 622, 622, 622, 622, 622, 622, 622, 622, 0, 0, 0, 0, 0, 0, 0, 135, 450, 450, 450, 450, 450,
		450, 450, 450, 450, 450, 450, 450, 450, 450, 450, 450, 450, 450, 450, 0, 0, 0, 0, 623, 623, 623,
		623, 623, 623, 623, 623, 624, 624, 624, 624, 0, 0, 0, 0, 0, 0, 0, 0, 0, 623, 623, 623, 625, 625,
		625, 625, 625, 625, 625, 625, 625, 626, 625, 625, 625, 625, 625, 625, 625, 625, 626, 0, 0, 0, 0,
		0, 627, 627, 627, 627, 627, 627, 627, 627, 627, 627, 627, 627, 627, 627, 628, 628, 628, 628, 628,
		0, 0, 0, 0, 0, 629, 629, 629, 629, 629, 629, 629, 629, 629, 629, 629, 629, 629, 629, 0, 630, 631,
		631, 631, 631, 631, 631, 631, 631, 631, 631, 631, 631, 0, 0, 0, 0, 632, 633, 633, 633, 633, 633,
		0, 0, 634, 634, 634, 634, 634, 634, 634, 634, 635, 635, 635, 635, 635, 635, 635, 635, 636, 636,
		636, 636, 636, 636, 636, 636, 637, 637, 637, 637, 637, 637, 637, 637, 637, 637, 637, 637, 637,
		637, 0, 0, 638, 638, 638, 638, 638, 638, 638, 638, 638, 638, 0, 0, 0, 0, 0, 0, 639, 639, 639, 639,
		639, 639, 639, 639, 639, 639, 639, 639, 0, 0, 0, 0, 640, 640, 640, 640, 640, 640, 640, 640, 640,
		640, 640, 640, 0, 0, 0, 0, 641, 641, 641, 641, 641, 641, 641, 641, 642, 642, 642, 642, 642, 642,
		642, 642, 642, 642, 642, 642, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 643, 644, 644, 644, 644, 644, 644,
		644, 644, 644, 644, 644, 0, 644, 644, 644, 644, 644, 644, 644, 0, 644, 644, 0, 645, 645, 645, 645,
		645, 645, 645, 645, 645, 645, 645, 0, 645, 645, 645, 645, 645, 645, 645, 0, 645, 645, 0, 0, 0,
		646, 646, 646, 646, 646, 646, 646, 646, 646, 646, 646, 646, 0, 0, 0, 0, 647, 647, 647, 647, 647,
		647, 647, 647, 647, 647, 647, 647, 647, 647, 647, 0, 647, 647, 647, 647, 647, 647, 0, 0, 44, 44,
		44, 44, 44, 44, 0, 44, 44, 0, 44, 44, 44, 44, 44, 44, 44, 44, 44, 0, 0, 0, 0, 0, 648, 648, 648,
		648, 648, 648, 86, 86, 648, 86, 648, 648, 648, 648, 648, 648, 648, 648, 648, 648, 648, 648, 648,
		648, 648, 648, 648, 648, 648, 648, 86, 648, 648, 86, 86, 86, 648, 86, 86, 648, 649, 649, 649, 649,
		649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 86, 650, 651, 651, 651, 651, 651, 651, 651, 651,
		652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 652, 653, 653, 654, 654,
		654, 654, 654, 654, 654, 655, 655, 655, 655, 655, 655, 655, 655, 655, 655, 655, 655, 655, 655,
		655, 86, 86, 86, 86, 86, 86, 86, 86, 656, 656, 656, 656, 656, 656, 656, 656, 656, 657, 657, 657,
		657, 657, 657, 657, 657, 657, 657, 657, 86, 657, 657, 86, 86, 86, 86, 86, 658, 658, 658, 658, 658,
		659, 659, 659, 659, 659, 659, 659, 659, 659, 659, 659, 659, 659, 659, 660, 660, 660, 660, 660,
		660, 86, 86, 86, 661, 662, 662, 662, 662, 662, 662, 662, 662, 662, 662, 86, 86, 86, 86, 86, 663,
		664, 664, 664, 664, 664, 664, 664, 664, 664, 664, 86, 86, 86, 86, 86, 86, 665, 665, 665, 665, 665,
		665, 665, 665, 666, 666, 666, 666, 666, 666, 666, 666, 86, 86, 86, 86, 667, 667, 666, 666, 667,
		667, 667, 667, 667, 667, 667, 667, 86, 86, 667, 667, 667, 667, 667, 667, 668, 669, 669, 669, 86,
		669, 669, 86, 86, 86, 86, 86, 669, 670, 669, 671, 668, 668, 668, 668, 86, 668, 668, 668, 86, 668,
		668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668, 668,
		668, 86, 86, 671, 672, 670, 86, 86, 86, 86, 673, 674, 674, 674, 674, 674, 674, 674, 674, 674, 86,
		86, 86, 86, 86, 86, 86, 675, 675, 675, 675, 675, 675, 675, 675, 675, 86, 86, 86, 86, 86, 86, 86,
		676, 676, 676, 676, 676, 676, 676, 676, 676, 676, 676, 676, 676, 677, 677, 678, 679, 679, 679,
		679, 679, 679, 679, 679, 679, 679, 679, 679, 679, 680, 680, 680, 681, 681, 681, 681, 681, 681,
		681, 681, 682, 681, 681, 681, 681, 681, 681, 681, 681, 681, 681, 681, 681, 683, 684, 86, 86, 86,
		86, 685, 685, 685, 685, 685, 686, 686, 686, 686, 686, 686, 686, 86, 687, 687, 687, 687, 687, 687,
		687, 687, 687, 687, 687, 687, 687, 687, 86, 86, 86, 688, 688, 688, 688, 688, 688, 688, 689, 689,
		689, 689, 689, 689, 689, 689, 689, 689, 689, 689, 689, 689, 86, 86, 690, 690, 690, 690, 690, 690,
		690, 690, 691, 691, 691, 691, 691, 691, 691, 691, 691, 691, 691, 86, 86, 86, 86, 86, 692, 692,
		692, 692, 692, 692, 692, 692, 693, 693, 693, 693, 693, 693, 693, 693, 693, 693, 86, 86, 86, 86,
		86, 86, 86, 694, 694, 694, 694, 86, 86, 86, 86, 695, 695, 695, 695, 695, 695, 695, 696, 696, 696,
		696, 696, 696, 696, 696, 696, 86, 86, 86, 86, 86, 86, 86, 697, 697, 697, 697, 697, 697, 697, 697,
		697, 697, 697, 86, 86, 86, 86, 86, 698, 698, 698, 698, 698, 698, 698, 698, 698, 698, 698, 86, 86,
		86, 86, 86, 86, 86, 699, 699, 699, 699, 699, 699, 700, 700, 700, 700, 700, 700, 700, 700, 700,
		700, 700, 700, 701, 701, 701, 701, 702, 702, 702, 702, 702, 702, 702, 702, 702, 702, 144, 144,
		144, 144, 144, 144, 703, 703, 703, 703, 703, 703, 703, 703, 703, 703, 704, 704, 704, 704, 705,
		704, 706, 706, 706, 706, 706, 706, 706, 706, 706, 706, 706, 706, 706, 706, 86, 86, 86, 707, 707,
		707, 707, 707, 708, 705, 709, 709, 709, 709, 709, 709, 709, 709, 709, 709, 709, 709, 709, 709, 86,
		86, 86, 86, 86, 86, 86, 86, 710, 710, 711, 711, 711, 711, 711, 711, 711, 711, 711, 711, 711, 711,
		711, 711, 711, 86, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 86, 713, 713, 714, 86, 86,
		712, 712, 86, 86, 86, 86, 86, 86, 144, 144, 125, 125, 125, 140, 125, 125, 715, 118, 118, 118, 118,
		118, 118, 118, 118, 144, 144, 144, 144, 144, 144, 144, 144, 144, 136, 136, 716, 136, 136, 136,
		717, 717, 717, 717, 717, 717, 717, 717, 717, 717, 717, 717, 717, 718, 718, 718, 718, 718, 718,
		718, 718, 718, 718, 717, 719, 719, 719, 719, 719, 719, 719, 719, 719, 719, 719, 719, 719, 719,
		720, 720, 721, 721, 721, 720, 721, 720, 720, 720, 720, 722, 722, 722, 722, 723, 723, 723, 723,
		723, 144, 144, 144, 144, 144, 144, 724, 724, 724, 724, 724, 724, 724, 724, 724, 724, 725, 726,
		725, 726, 727, 727, 727, 727, 86, 86, 86, 86, 86, 86, 728, 728, 728, 728, 728, 728, 728, 728, 728,
		728, 728, 728, 728, 729, 729, 729, 729, 729, 729, 729, 86, 86, 86, 86, 730, 730, 730, 730, 730,
		730, 730, 730, 730, 730, 730, 730, 730, 730, 730, 86, 731, 732, 731, 733, 733, 733, 733, 733, 733,
		733, 733, 733, 733, 733, 733, 733, 732, 732, 732, 732, 732, 732, 732, 732, 732, 732, 732, 732,
		732, 732, 734, 735, 735, 735, 735, 735, 735, 735, 0, 0, 0, 0, 736, 736, 736, 736, 736, 736, 736,
		736, 736, 736, 736, 736, 736, 736, 736, 736, 736, 736, 736, 736, 737, 737, 737, 737, 737, 737,
		737, 737, 737, 737, 734, 733, 733, 732, 732, 733, 0, 0, 0, 0, 0, 0, 0, 0, 0, 734, 738, 738, 739,
		740, 740, 740, 740, 740, 740, 740, 740, 740, 740, 740, 740, 740, 739, 739, 739, 738, 738, 738,
		738, 739, 739, 741, 742, 743, 743, 744, 743, 743, 743, 743, 738, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
		744, 0, 0, 745, 745, 745, 745, 745, 745, 745, 745, 745, 0, 0, 0, 0, 0, 0, 0, 746, 746, 746, 746,
		746, 746, 746, 746, 746, 746, 0, 0, 0, 0, 0, 0, 747, 747, 747, 748, 748, 748, 748, 748, 748, 748,
		748, 748, 748, 748, 748, 748, 748, 748, 748, 748, 748, 748, 748, 749, 749, 749, 749, 749, 750,
		749, 749, 749, 749, 749, 749, 751, 751, 0, 752, 752, 752, 752, 752, 752, 752, 752, 752, 752, 753,
		753, 753, 753, 748, 750, 750, 748, 754, 754, 754, 754, 754, 754, 754, 754, 754, 754, 754, 755,
		756, 756, 754, 0, 757, 757, 758, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759,
		759, 759, 759, 758, 758, 758, 757, 757, 757, 757, 757, 757, 757, 757, 757, 758, 760, 759, 759,
		759, 759, 761, 761, 761, 761, 757, 762, 757, 757, 761, 758, 757, 763, 763, 763, 763, 763, 763,
		763, 763, 763, 763, 759, 761, 759, 761, 761, 761, 0, 764, 764, 764, 764, 764, 764, 764, 764, 764,
		764, 764, 764, 764, 764, 764, 764, 764, 764, 764, 764, 0, 0, 0, 765, 765, 765, 765, 765, 765, 765,
		765, 765, 765, 0, 765, 765, 765, 765, 765, 765, 765, 765, 765, 766, 766, 766, 767, 767, 767, 766,
		766, 767, 768, 769, 767, 770, 770, 770, 770, 770, 770, 767, 765, 765, 767, 0, 0, 0, 0, 0, 0, 771,
		771, 771, 771, 771, 771, 771, 0, 771, 0, 771, 771, 771, 771, 0, 771, 771, 771, 771, 771, 771, 771,
		771, 771, 771, 771, 771, 771, 771, 771, 0, 771, 771, 772, 0, 0, 0, 0, 0, 0, 773, 773, 773, 773,
		773, 773, 773, 773, 773, 773, 773, 773, 773, 773, 773, 774, 775, 775, 775, 774, 774, 774, 774,
		774, 774, 776, 777, 0, 0, 0, 0, 0, 778, 778, 778, 778, 778, 778, 778, 778, 778, 778, 0, 0, 0, 0,
		0, 0, 779, 779, 780, 780, 0, 781, 781, 781, 781, 781, 781, 781, 781, 0, 0, 781, 781, 0, 0, 781,
		781, 781, 781, 781, 781, 781, 781, 781, 781, 781, 781, 781, 781, 0, 781, 781, 781, 781, 781, 781,
		781, 0, 781, 781, 0, 781, 781, 781, 781, 781, 0, 782, 783, 781, 780, 780, 779, 780, 780, 780, 780,
		0, 0, 780, 780, 0, 0, 780, 780, 784, 0, 0, 781, 0, 0, 0, 0, 0, 0, 780, 0, 0, 0, 0, 0, 781, 781,
		781, 781, 781, 780, 780, 0, 0, 785, 785, 785, 785, 785, 785, 785, 0, 0, 0, 786, 786, 786, 786,
		786, 786, 786, 786, 786, 786, 0, 786, 0, 0, 786, 0, 786, 786, 786, 786, 786, 786, 0, 786, 787,
		787, 787, 788, 788, 788, 788, 788, 788, 0, 787, 0, 0, 787, 0, 787, 787, 787, 787, 0, 787, 787,
		789, 790, 789, 786, 788, 786, 791, 791, 0, 791, 791, 0, 0, 0, 0, 0, 0, 0, 0, 788, 788, 0, 0, 0, 0,
		0, 792, 792, 792, 792, 792, 792, 792, 792, 792, 792, 792, 792, 792, 793, 793, 793, 794, 794, 794,
		794, 794, 794, 794, 794, 793, 793, 795, 794, 794, 793, 796, 792, 792, 792, 792, 797, 797, 797,
		797, 797, 798, 798, 798, 798, 798, 798, 798, 798, 798, 798, 797, 797, 0, 797, 799, 792, 792, 792,
		0, 0, 0, 0, 0, 0, 800, 800, 800, 800, 800, 800, 800, 800, 801, 801, 801, 802, 802, 802, 802, 802,
		802, 801, 802, 801, 801, 801, 801, 802, 802, 801, 803, 804, 800, 800, 805, 800, 806, 806, 806,
		806, 806, 806, 806, 806, 806, 806, 0, 0, 0, 0, 0, 0, 807, 807, 807, 807, 807, 807, 807, 807, 807,
		807, 807, 807, 807, 807, 807, 808, 808, 808, 809, 809, 809, 809, 0, 0, 808, 808, 808, 808, 809,
		809, 808, 810, 811, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812, 812,
		807, 807, 807, 807, 809, 809, 0, 0, 813, 813, 813, 813, 813, 813, 813, 813, 814, 814, 814, 815,
		815, 815, 815, 815, 815, 815, 815, 814, 814, 815, 814, 816, 815, 817, 817, 817, 813, 0, 0, 0, 818,
		818, 818, 818, 818, 818, 818, 818, 818, 818, 0, 0, 0, 0, 0, 0, 339, 339, 339, 339, 339, 339, 339,
		339, 339, 339, 339, 339, 339, 0, 0, 0, 819, 819, 819, 819, 819, 819, 819, 819, 819, 819, 819, 820,
		821, 820, 821, 821, 820, 820, 820, 820, 820, 820, 822, 823, 819, 824, 0, 0, 0, 0, 0, 0, 825, 825,
		825, 825, 825, 825, 825, 825, 825, 825, 0, 0, 0, 0, 0, 0, 292, 292, 292, 292, 0, 0, 0, 0, 826,
		826, 826, 826, 826, 826, 826, 826, 826, 826, 826, 0, 0, 827, 828, 827, 828, 828, 827, 827, 827,
		827, 828, 827, 827, 827, 827, 829, 0, 0, 0, 0, 830, 830, 830, 830, 830, 830, 830, 830, 830, 830,
		831, 831, 832, 832, 832, 833, 826, 826, 826, 826, 826, 826, 826, 0, 834, 834, 834, 834, 834, 834,
		834, 834, 834, 834, 834, 834, 835, 835, 835, 836, 836, 836, 836, 836, 836, 836, 836, 836, 835,
		837, 838, 839, 0, 0, 0, 0, 840, 840, 840, 840, 840, 840, 840, 840, 841, 841, 841, 841, 841, 841,
		841, 841, 842, 842, 842, 842, 842, 842, 842, 842, 842, 842, 843, 843, 843, 843, 843, 843, 843,
		843, 843, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 844, 845, 845, 845, 845, 845, 845, 845, 0, 0, 845,
		0, 0, 845, 845, 845, 845, 845, 845, 845, 845, 0, 845, 845, 0, 845, 845, 845, 845, 845, 845, 845,
		845, 846, 846, 846, 846, 846, 846, 0, 846, 846, 0, 0, 847, 847, 848, 849, 845, 846, 845, 846, 850,
		851, 851, 851, 0, 852, 852, 852, 852, 852, 852, 852, 852, 852, 852, 0, 0, 0, 0, 0, 0, 853, 853,
		853, 853, 853, 853, 853, 853, 0, 0, 853, 853, 853, 853, 853, 853, 853, 854, 854, 854, 855, 855,
		855, 855, 0, 0, 855, 855, 854, 854, 854, 854, 856, 853, 857, 853, 854, 0, 0, 0, 858, 859, 859,
		859, 859, 859, 859, 860, 860, 859, 859, 858, 858, 858, 858, 858, 858, 858, 858, 858, 858, 858,
		858, 858, 858, 858, 858, 859, 861, 859, 859, 859, 859, 862, 858, 859, 859, 859, 859, 863, 863,
		863, 863, 863, 863, 863, 863, 861, 864, 865, 865, 865, 865, 865, 865, 866, 866, 865, 865, 865,
		864, 864, 864, 864, 864, 864, 864, 864, 864, 864, 864, 864, 864, 864, 865, 865, 865, 865, 865,
		865, 865, 865, 865, 865, 865, 865, 865, 866, 865, 867, 868, 868, 868, 864, 868, 868, 868, 868,
		868, 0, 0, 0, 0, 0, 869, 869, 869, 869, 869, 869, 869, 869, 869, 0, 0, 0, 0, 0, 0, 0, 178, 178,
		178, 178, 178, 178, 178, 178, 178, 178, 0, 0, 0, 0, 0, 0, 757, 758, 757, 757, 757, 758, 757, 758,
		870, 870, 870, 870, 870, 870, 870, 870, 870, 871, 0, 0, 0, 0, 0, 0, 872, 872, 872, 872, 872, 872,
		872, 872, 872, 872, 0, 0, 0, 0, 0, 0, 873, 873, 873, 873, 873, 873, 873, 873, 873, 0, 873, 873,
		873, 873, 873, 873, 873, 873, 873, 873, 873, 873, 873, 874, 875, 875, 875, 875, 875, 875, 875, 0,
		875, 875, 875, 875, 875, 875, 874, 876, 873, 877, 877, 877, 877, 877, 0, 0, 878, 878, 878, 878,
		878, 878, 878, 878, 878, 878, 879, 879, 879, 879, 879, 879, 879, 879, 879, 879, 879, 879, 879,
		879, 879, 879, 879, 879, 879, 0, 0, 0, 880, 880, 881, 881, 881, 881, 881, 881, 881, 881, 881, 881,
		881, 881, 881, 881, 0, 0, 882, 882, 882, 882, 882, 882, 882, 882, 882, 882, 882, 882, 882, 882, 0,
		883, 882, 882, 882, 882, 882, 882, 882, 883, 882, 882, 883, 882, 882, 0, 884, 884, 884, 884, 884,
		884, 884, 0, 884, 884, 0, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884, 884,
		885, 885, 885, 885, 885, 885, 0, 0, 0, 885, 0, 885, 885, 0, 885, 885, 885, 886, 885, 887, 887,
		884, 885, 888, 888, 888, 888, 888, 888, 888, 888, 888, 888, 0, 0, 0, 0, 0, 0, 889, 889, 889, 889,
		889, 889, 0, 889, 889, 0, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889,
		889, 889, 890, 890, 890, 890, 890, 0, 891, 891, 0, 890, 890, 891, 890, 892, 889, 0, 0, 0, 0, 0, 0,
		0, 893, 893, 893, 893, 893, 893, 893, 893, 893, 893, 0, 0, 0, 0, 0, 0, 894, 894, 894, 894, 894,
		894, 894, 894, 894, 895, 894, 894, 0, 0, 0, 0, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896,
		0, 0, 0, 0, 0, 0, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 897, 898, 898, 899, 899, 900,
		900, 0, 0, 0, 0, 0, 0, 0, 901, 901, 902, 903, 902, 902, 902, 902, 902, 902, 902, 902, 902, 902,
		902, 902, 902, 0, 902, 902, 902, 902, 902, 902, 902, 902, 902, 902, 903, 903, 901, 901, 901, 901,
		901, 0, 0, 0, 903, 903, 901, 904, 905, 906, 906, 906, 906, 906, 906, 906, 906, 906, 906, 906, 906,
		906, 907, 907, 907, 907, 907, 907, 907, 907, 907, 907, 901, 0, 0, 0, 0, 0, 514, 0, 0, 0, 0, 0, 0,
		0, 219, 219, 219, 219, 219, 219, 219, 219, 219, 219, 219, 219, 219, 220, 220, 220, 220, 220, 220,
		220, 220, 221, 221, 221, 221, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220,
		220, 220, 220, 220, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 908, 909, 909, 909, 909, 909, 909, 909,
		909, 909, 909, 0, 0, 0, 0, 0, 0, 910, 910, 910, 910, 910, 910, 910, 910, 910, 910, 910, 910, 910,
		910, 910, 0, 911, 911, 911, 911, 911, 0, 0, 0, 909, 909, 909, 909, 0, 0, 0, 0, 912, 912, 912, 912,
		912, 912, 912, 912, 912, 913, 913, 0, 0, 0, 0, 0, 914, 914, 914, 914, 914, 914, 914, 914, 915,
		915, 915, 915, 915, 915, 915, 915, 916, 914, 914, 914, 914, 914, 914, 916, 916, 916, 916, 916,
		916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 0, 0, 914, 914, 914, 0, 0, 0, 0, 0, 917, 917,
		917, 917, 917, 917, 917, 917, 917, 917, 917, 917, 917, 917, 917, 0, 918, 918, 918, 918, 918, 918,
		918, 918, 918, 918, 918, 918, 918, 918, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919,
		919, 920, 920, 920, 919, 919, 921, 922, 922, 922, 922, 922, 922, 922, 922, 922, 922, 0, 0, 0, 0,
		0, 0, 524, 0, 0, 0, 0, 0, 0, 0, 923, 923, 923, 923, 923, 923, 923, 923, 923, 923, 923, 923, 923,
		923, 923, 0, 924, 924, 924, 924, 924, 924, 924, 924, 924, 924, 0, 0, 0, 0, 925, 925, 926, 926,
		926, 926, 926, 926, 926, 926, 926, 926, 926, 926, 926, 926, 926, 0, 927, 927, 927, 927, 927, 927,
		927, 927, 927, 927, 0, 0, 0, 0, 0, 0, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928,
		928, 928, 0, 0, 929, 929, 929, 929, 929, 930, 0, 0, 931, 931, 931, 931, 931, 931, 931, 931, 932,
		932, 932, 932, 932, 932, 932, 933, 933, 933, 933, 933, 934, 934, 934, 934, 935, 935, 935, 935,
		933, 934, 0, 0, 936, 936, 936, 936, 936, 936, 936, 936, 936, 936, 0, 937, 937, 937, 937, 937, 937,
		937, 0, 931, 931, 931, 931, 931, 0, 0, 0, 0, 0, 931, 931, 931, 938, 938, 938, 939, 939, 939, 939,
		939, 939, 939, 939, 939, 939, 939, 939, 939, 939, 939, 939, 938, 938, 940, 940, 940, 941, 941,
		941, 941, 941, 941, 941, 941, 941, 941, 0, 0, 0, 0, 0, 0, 942, 942, 942, 942, 942, 942, 942, 942,
		943, 943, 943, 943, 943, 943, 943, 943, 944, 944, 944, 944, 944, 944, 944, 944, 944, 944, 944,
		944, 944, 944, 944, 945, 945, 945, 945, 0, 0, 0, 0, 0, 946, 946, 946, 946, 946, 946, 946, 946,
		946, 0, 0, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 947, 0,
		0, 0, 0, 948, 948, 948, 948, 948, 948, 948, 948, 948, 948, 948, 0, 0, 0, 0, 949, 948, 950, 950,
		950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 0, 0, 0, 0, 0, 0, 0, 949, 949,
		949, 949, 951, 951, 951, 951, 951, 951, 951, 951, 951, 951, 951, 951, 951, 952, 953, 954, 484,
		955, 0, 0, 0, 956, 956, 484, 484, 486, 486, 486, 0, 957, 957, 957, 957, 957, 957, 957, 957, 958,
		958, 958, 958, 958, 958, 958, 958, 958, 958, 958, 958, 958, 958, 0, 0, 0, 0, 0, 0, 0, 0, 0, 958,
		957, 957, 957, 957, 957, 957, 957, 0, 957, 957, 957, 0, 0, 0, 0, 0, 501, 501, 501, 501, 0, 501,
		501, 501, 501, 501, 501, 501, 0, 501, 501, 0, 500, 496, 496, 496, 496, 496, 496, 496, 500, 500,
		500, 0, 0, 0, 0, 0, 0, 0, 496, 0, 0, 0, 0, 0, 496, 496, 496, 0, 0, 500, 0, 0, 0, 0, 0, 0, 500,
		500, 500, 500, 959, 959, 959, 959, 959, 959, 959, 959, 959, 959, 959, 959, 0, 0, 0, 0, 960, 960,
		960, 960, 960, 960, 960, 960, 960, 960, 960, 0, 0, 0, 0, 0, 960, 960, 960, 960, 960, 0, 0, 0, 960,
		0, 0, 0, 0, 0, 0, 0, 960, 960, 0, 0, 961, 962, 963, 964, 424, 424, 424, 424, 0, 0, 0, 0, 965, 965,
		965, 965, 965, 965, 965, 965, 965, 965, 26, 26, 26, 0, 0, 0, 26, 26, 26, 26, 0, 0, 0, 0, 0, 0, 26,
		26, 26, 26, 26, 26, 26, 0, 0, 0, 0, 0, 0, 0, 447, 0, 0, 0, 0, 0, 0, 0, 966, 966, 966, 966, 966,
		966, 966, 966, 966, 966, 966, 966, 966, 966, 0, 0, 966, 966, 966, 966, 966, 966, 966, 0, 286, 286,
		286, 286, 0, 0, 0, 0, 286, 286, 286, 286, 286, 286, 0, 0, 286, 286, 286, 286, 286, 286, 286, 0, 0,
		286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 967, 967, 412, 412, 412, 286, 286,
		286, 968, 967, 967, 967, 967, 967, 424, 424, 424, 424, 424, 424, 424, 424, 135, 135, 135, 135,
		135, 135, 135, 135, 286, 286, 77, 77, 77, 77, 77, 135, 135, 286, 286, 286, 286, 286, 286, 77, 77,
		77, 77, 286, 286, 286, 26, 26, 0, 0, 0, 0, 0, 619, 619, 969, 969, 969, 619, 0, 0, 534, 534, 534,
		534, 0, 0, 0, 0, 464, 464, 464, 464, 464, 464, 464, 0, 504, 504, 504, 504, 504, 504, 504, 534,
		534, 0, 0, 0, 0, 0, 0, 0, 455, 455, 455, 455, 455, 455, 455, 455, 455, 455, 34, 34, 34, 34, 34,
		34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 455, 455, 455, 455, 455, 455, 455, 455, 455,
		455, 34, 34, 34, 34, 34, 34, 34, 0, 34, 34, 34, 34, 34, 34, 455, 0, 455, 455, 0, 0, 455, 0, 0,
		455, 455, 0, 0, 455, 455, 455, 455, 0, 455, 455, 34, 34, 0, 34, 0, 34, 34, 34, 34, 34, 34, 34, 0,
		34, 34, 34, 34, 34, 34, 34, 455, 455, 0, 455, 455, 455, 455, 0, 0, 455, 455, 455, 455, 455, 455,
		455, 455, 0, 455, 455, 455, 455, 455, 455, 455, 0, 34, 34, 455, 455, 0, 455, 455, 455, 455, 0,
		455, 455, 455, 455, 455, 0, 455, 0, 0, 0, 455, 455, 455, 455, 455, 455, 455, 0, 34, 34, 34, 34,
		34, 34, 34, 34, 34, 34, 34, 34, 0, 0, 455, 447, 34, 34, 34, 34, 34, 34, 34, 34, 34, 458, 34, 34,
		34, 34, 34, 34, 455, 455, 455, 455, 455, 455, 455, 455, 455, 447, 34, 34, 34, 34, 34, 34, 34, 34,
		34, 458, 34, 34, 455, 455, 455, 455, 455, 447, 34, 34, 34, 34, 34, 34, 34, 34, 34, 458, 34, 34,
		34, 34, 34, 34, 455, 455, 455, 455, 455, 455, 455, 455, 455, 447, 34, 458, 34, 34, 34, 34, 34, 34,
		34, 34, 455, 34, 0, 0, 965, 965, 970, 970, 970, 970, 970, 970, 970, 970, 971, 971, 971, 971, 971,
		971, 971, 971, 971, 971, 971, 971, 971, 971, 971, 970, 970, 970, 970, 971, 971, 971, 971, 971,
		971, 971, 971, 971, 971, 970, 970, 970, 970, 970, 970, 970, 970, 971, 970, 970, 970, 970, 970,
		970, 971, 970, 970, 972, 972, 972, 972, 972, 0, 0, 0, 0, 0, 0, 0, 971, 971, 971, 971, 971, 0, 971,
		971, 971, 971, 971, 971, 971, 41, 41, 42, 41, 41, 41, 41, 41, 0, 0, 0, 0, 0, 41, 41, 41, 41, 41,
		41, 0, 0, 0, 0, 0, 973, 973, 973, 973, 973, 973, 973, 0, 973, 973, 973, 973, 973, 973, 973, 973,
		973, 0, 0, 973, 973, 973, 973, 973, 973, 973, 0, 973, 973, 0, 973, 973, 973, 973, 973, 0, 0, 0, 0,
		0, 415, 415, 415, 415, 415, 415, 415, 415, 415, 415, 415, 415, 415, 415, 0, 0, 0, 0, 0, 0, 0, 0,
		0, 76, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 0, 0, 0, 975, 975, 975,
		975, 975, 975, 975, 976, 976, 976, 976, 976, 976, 976, 0, 0, 977, 977, 977, 977, 977, 977, 977,
		977, 977, 977, 0, 0, 0, 0, 974, 978, 979, 979, 979, 979, 979, 979, 979, 979, 979, 979, 979, 979,
		979, 979, 980, 0, 981, 981, 981, 981, 981, 981, 981, 981, 981, 981, 981, 981, 982, 982, 982, 982,
		983, 983, 983, 983, 983, 983, 983, 983, 983, 983, 0, 0, 0, 0, 0, 984, 985, 985, 985, 985, 985,
		985, 985, 985, 985, 985, 985, 986, 987, 987, 988, 989, 990, 990, 990, 990, 990, 990, 990, 990,
		990, 990, 0, 0, 0, 0, 0, 0, 991, 991, 991, 991, 991, 991, 991, 991, 991, 991, 991, 991, 991, 991,
		992, 993, 991, 994, 994, 994, 994, 994, 994, 994, 994, 994, 994, 0, 0, 0, 0, 995, 996, 996, 996,
		996, 996, 996, 996, 996, 996, 996, 996, 996, 996, 996, 996, 0, 996, 996, 996, 997, 996, 996, 997,
		996, 996, 996, 996, 996, 996, 996, 997, 997, 996, 996, 996, 996, 996, 997, 0, 0, 0, 0, 0, 0, 0, 0,
		996, 998, 301, 301, 301, 301, 0, 301, 301, 0, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999,
		999, 999, 999, 86, 86, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1001, 1001, 1001,
		1001, 1001, 1001, 1001, 86, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1003,
		1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003, 1003,
		1003, 1004, 1004, 1004, 1004, 1004, 1004, 1005, 1006, 86, 86, 86, 86, 1007, 1007, 1007, 1007,
		1007, 1007, 1007, 1007, 1007, 1007, 86, 86, 86, 86, 1008, 1008, 144, 1009, 1009, 1009, 1009, 1009,
		1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1009, 1010, 1009,
		1009, 1009, 1011, 1009, 1009, 1009, 1009, 144, 144, 144, 1009, 1009, 1009, 1009, 1009, 1009, 1010,
		1009, 1009, 1009, 1009, 1009, 1009, 1009, 144, 144, 125, 125, 125, 125, 144, 125, 125, 125, 144,
		125, 125, 144, 125, 144, 144, 125, 144, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 144,
		125, 125, 125, 125, 144, 125, 144, 125, 144, 144, 144, 144, 144, 144, 125, 144, 144, 144, 144,
		125, 144, 125, 144, 125, 144, 125, 125, 125, 144, 125, 144, 125, 144, 125, 144, 125, 144, 125,
		125, 125, 125, 144, 125, 144, 125, 125, 144, 125, 125, 125, 125, 125, 125, 125, 125, 125, 144,
		144, 144, 144, 144, 125, 125, 125, 144, 125, 125, 125, 112, 112, 144, 144, 144, 144, 144, 144, 26,
		26, 26, 26, 464, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 0, 0, 26, 26, 26, 26, 26, 26, 26, 26, 26,
		26, 26, 26, 26, 0, 0, 33, 33, 33, 459, 459, 26, 26, 26, 467, 467, 467, 467, 467, 467, 286, 26,
		467, 467, 26, 26, 26, 26, 26, 26, 467, 467, 467, 467, 467, 467, 503, 467, 467, 503, 503, 503, 503,
		503, 503, 503, 503, 503, 503, 467, 467, 467, 467, 467, 467, 467, 467, 467, 467, 26, 0, 0, 0, 0, 0,
		0, 0, 0, 286, 286, 1012, 503, 503, 0, 0, 0, 0, 0, 503, 503, 503, 503, 0, 0, 0, 0, 503, 0, 0, 0, 0,
		0, 0, 0, 503, 503, 0, 0, 0, 0, 0, 0, 26, 26, 26, 26, 26, 464, 464, 464, 464, 464, 464, 464, 464,
		464, 26, 464, 464, 464, 464, 464, 464, 26, 464, 464, 464, 464, 464, 26, 26, 26, 26, 464, 464, 26,
		26, 26, 464, 26, 26, 26, 464, 464, 464, 498, 498, 498, 498, 498, 464, 464, 464, 464, 464, 464,
		464, 26, 464, 26, 464, 464, 464, 464, 464, 464, 464, 464, 464, 464, 464, 26, 26, 464, 464, 464,
		464, 464, 464, 464, 26, 26, 26, 26, 26, 464, 464, 464, 464, 26, 26, 26, 464, 26, 26, 26, 26, 26,
		26, 26, 26, 26, 26, 464, 464, 26, 26, 26, 26, 464, 464, 464, 464, 464, 464, 464, 464, 26, 26, 464,
		464, 464, 464, 0, 0, 0, 464, 464, 464, 464, 26, 26, 26, 464, 464, 0, 0, 0, 26, 26, 26, 26, 464,
		464, 464, 464, 464, 464, 464, 464, 464, 0, 0, 0, 464, 464, 464, 464, 0, 0, 0, 0, 464, 0, 0, 0, 0,
		0, 0, 0, 464, 464, 464, 26, 464, 464, 464, 464, 464, 464, 464, 0, 0, 0, 464, 464, 464, 0, 0, 0, 0,
		464, 464, 464, 464, 464, 464, 464, 464, 0, 0, 464, 464, 464, 464, 0, 0, 0, 0, 464, 26, 26, 26, 0,
		26, 26, 26, 26, 965, 965, 26, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 448, 448, 510, 581, 581, 581, 581,
		581, 581, 581, 581, 581, 581, 581, 581, 581, 448, 448, 510, 510, 510, 581, 581, 581, 581, 581,
		448, 424, 448, 448, 448, 448, 448, 448, 424, 424, 424, 424, 424, 424, 424, 424, 580, 580, 580,
		580, 580, 580, 448, 448,
	];
}
