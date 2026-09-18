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
	const SCRIPT_JURCHEN = 175;
	const SCRIPT_PROTO_CUNEIFORM = 176;
	const SCRIPT_SEAL = 177;

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
		/* SCRIPT_JURCHEN */ 175 => 'jurc',
		/* SCRIPT_PROTO_CUNEIFORM */ 176 => 'pcun',
		/* SCRIPT_SEAL */ 177 => 'seal',
	];

	// HARFBUZZ_VERSION 14.3.1
	/**
	 * The OpenType language system tags of a language code, in the order a font is to be asked for
	 * them, generated from HarfBuzz's own table - see tests/Mpdf/OtLanguageTags.php. Each value holds
	 * the tags end to end, four characters apiece; an empty one is a code HarfBuzz refuses to map
	 * because an unrelated language has the tag it would take, and the comment names the collision.
	 *
	 * Shaper\OtlTags holds the rest of HarfBuzz's mapping, which is not rows of a table: a three-letter
	 * code with no key here is used upper-cased as its own tag, and a tag of several subtags has rules
	 * of its own.
	 *
	 * @var string[]
	 */
	public static $ot_languages = [
		'aa' => 'AFR ', /* Afar */
		'aae' => 'SQI ', /* Arbëreshë Albanian */
		'aao' => 'ARA ', /* Algerian Saharan Arabic */
		'aat' => 'SQI ', /* Arvanitika Albanian */
		'ab' => 'ABK ', /* Abkhazian */
		'aba' => '', /* Abé != Abaza */
		'abh' => 'ARA ', /* Tajiki Arabic */
		'abq' => 'ABA ', /* Abaza */
		'abs' => 'CPP ', /* Ambonese Malay */
		'abv' => 'ARA ', /* Baharna Arabic */
		'acf' => 'FAN CPP ', /* Saint Lucian Creole French */
		'acm' => 'ARA ', /* Mesopotamian Arabic */
		'acq' => 'ARA ', /* Ta'izzi-Adeni Arabic */
		'acr' => 'ACR MYN ', /* Achi */
		'acw' => 'ARA ', /* Hijazi Arabic */
		'acx' => 'ARA ', /* Omani Arabic */
		'acy' => 'ACY ARA ', /* Cypriot Arabic */
		'ada' => 'DNG ', /* Adangme */
		'adf' => 'ARA ', /* Dhofari Arabic */
		'adp' => 'DZN ', /* Adap (retired code) */
		'aeb' => 'ARA ', /* Tunisian Arabic */
		'aec' => 'ARA ', /* Saidi Arabic */
		'af' => 'AFK ', /* Afrikaans */
		'afb' => 'ARA ', /* Gulf Arabic */
		'afk' => '', /* Nanubae != Afrikaans */
		'afs' => 'CPP ', /* Afro-Seminole Creole */
		'agu' => 'MYN ', /* Aguacateco */
		'agw' => '', /* Kahua != Agaw */
		'ahg' => 'AGW ', /* Qimant */
		'aht' => 'ATH ', /* Ahtena */
		'aig' => 'CPP ', /* Antigua and Barbuda Creole English */
		'aii' => 'SWA SYR ', /* Assyrian Neo-Aramaic */
		'aiw' => 'ARI ', /* Aari */
		'ajp' => 'ARA ', /* South Levantine Arabic (retired code) */
		'ajt' => 'ARA ', /* Judeo-Tunisian Arabic (retired code) */
		'ak' => 'AKA ', /* Akan [macrolanguage] */
		'akb' => 'AKB BTK ', /* Batak Angkola */
		'aln' => 'SQI ', /* Gheg Albanian */
		'als' => 'SQI ', /* Tosk Albanian */
		'am' => 'AMH ', /* Amharic */
		'amf' => 'HBN ', /* Hamer-Banna */
		'amw' => 'SYR ', /* Western Neo-Aramaic */
		'an' => 'ARG ', /* Aragonese */
		'aoa' => 'CPP ', /* Angolar */
		'apa' => 'ATH ', /* Apache [collection] */
		'apc' => 'ARA ', /* Levantine Arabic */
		'apd' => 'ARA ', /* Sudanese Arabic */
		'apj' => 'ATH ', /* Jicarilla Apache */
		'apk' => 'ATH ', /* Kiowa Apache */
		'apl' => 'ATH ', /* Lipan Apache */
		'apm' => 'ATH ', /* Mescalero-Chiricahua Apache */
		'apw' => 'ATH ', /* Western Apache */
		'ar' => 'ARA ', /* Arabic [macrolanguage] */
		'arb' => 'ARA ', /* Standard Arabic */
		'ari' => '', /* Arikara != Aari */
		'ark' => '', /* Arikapú != Rakhine */
		'arn' => 'MAP ', /* Mapudungun */
		'arq' => 'ARA ', /* Algerian Arabic */
		'ars' => 'ARA ', /* Najdi Arabic */
		'ary' => 'MOR ARA ', /* Moroccan Arabic */
		'arz' => 'ARA ', /* Egyptian Arabic */
		'as' => 'ASM ', /* Assamese */
		'atj' => 'RCR ', /* Atikamekw */
		'atv' => 'ALT ', /* Northern Altai */
		'auj' => 'BBR ', /* Awjilah */
		'auz' => 'ARA ', /* Uzbeki Arabic */
		'av' => 'AVR ', /* Avaric */
		'avl' => 'ARA ', /* Eastern Egyptian Bedawi Arabic */
		'ay' => 'AYM ', /* Aymara [macrolanguage] */
		'ayc' => 'AYM ', /* Southern Aymara */
		'ayh' => 'ARA ', /* Hadrami Arabic */
		'ayl' => 'ARA ', /* Libyan Arabic */
		'ayn' => 'ARA ', /* Sanaani Arabic */
		'ayp' => 'ARA ', /* North Mesopotamian Arabic */
		'ayr' => 'AYM ', /* Central Aymara */
		'az' => 'AZE ', /* Azerbaijani [macrolanguage] */
		'azb' => 'AZB AZE ', /* South Azerbaijani */
		'azd' => 'NAH ', /* Eastern Durango Nahuatl */
		'azj' => 'AZE ', /* North Azerbaijani */
		'azn' => 'NAH ', /* Western Durango Nahuatl */
		'azz' => 'NAH ', /* Highland Puebla Nahuatl */
		'ba' => 'BSH ', /* Bashkir */
		'bad' => 'BAD0', /* Banda [collection] */
		'bag' => '', /* Tuki != Baghelkhandi */
		'bah' => 'CPP ', /* Bahamas Creole English */
		'bai' => 'BML ', /* Bamileke [collection] */
		'bal' => 'BLI ', /* Baluchi [macrolanguage] */
		'bau' => '', /* Bada (Nigeria) != Baulé */
		'bbc' => 'BBC BTK ', /* Batak Toba */
		'bbj' => 'BML ', /* Ghomálá' */
		'bbp' => 'BAD0', /* West Central Banda */
		'bbr' => '', /* Girawa != Berber */
		'bbz' => 'ARA ', /* Babalia Creole Arabic (retired code) */
		'bcc' => 'BLI ', /* Southern Balochi */
		'bch' => '', /* Bariai != Bench */
		'bci' => 'BAU ', /* Baoulé */
		'bcl' => 'BIK ', /* Central Bikol */
		'bcq' => 'BCH ', /* Bench */
		'bcr' => 'ATH ', /* Babine */
		'be' => 'BEL ', /* Belarusian */
		'bea' => 'ATH ', /* Beaver */
		'beb' => 'BTI ', /* Bebele */
		'ber' => 'BBR ', /* Berber [collection] */
		'bew' => 'CPP ', /* Betawi */
		'bfl' => 'BAD0', /* Banda-Ndélé */
		'bfq' => 'BAD ', /* Badaga */
		'bft' => 'BLT ', /* Balti */
		'bfu' => 'LAH ', /* Gahri */
		'bfy' => 'BAG ', /* Bagheli */
		'bg' => 'BGR ', /* Bulgarian */
		'bgn' => 'BLI ', /* Western Balochi */
		'bgp' => 'BLI ', /* Eastern Balochi */
		'bgq' => 'BGQ RAJ ', /* Bagri */
		'bgr' => 'QIN ', /* Bawm Chin */
		'bhb' => 'BHI ', /* Bhili */
		'bhk' => 'BIK ', /* Albay Bicolano (retired code) */
		'bhr' => 'MLG ', /* Bara Malagasy */
		'bi' => 'BIS CPP ', /* Bislama */
		'bil' => '', /* Bile != Bilen */
		'bin' => 'EDO ', /* Edo */
		'biu' => 'QIN ', /* Biete */
		'bjn' => 'MLY ', /* Banjar */
		'bjo' => 'BAD0', /* Mid-Southern Banda */
		'bjq' => 'MLG ', /* Southern Betsimisaraka Malagasy (retired code) */
		'bjs' => 'CPP ', /* Bajan */
		'bjt' => 'BLN ', /* Balanta-Ganja */
		'bkf' => '', /* Beeke != Blackfoot */
		'bko' => 'BML ', /* Kwa' */
		'bla' => 'BKF ', /* Siksika */
		'ble' => 'BLN ', /* Balanta-Kentohe */
		'blg' => 'IBA ', /* Balau (retired code) */
		'bli' => '', /* Bolia != Baluchi */
		'blk' => 'BLK KRN ', /* Pa’o Karen */
		'bln' => 'BIK ', /* Southern Catanduanes Bikol */
		'blt' => '', /* Tai Dam != Balti */
		'bm' => 'BMB ', /* Bambara (Bamanankan) */
		'bmb' => '', /* Bembe != Bambara (Bamanankan) */
		'bml' => '', /* Bomboli != Bamileke */
		'bmm' => 'MLG ', /* Northern Betsimisaraka Malagasy */
		'bn' => 'BEN ', /* Bangla */
		'bo' => 'TIB ', /* Tibetan */
		'bpd' => 'BAD0', /* Banda-Banda */
		'bpl' => 'CPP ', /* Broome Pearling Lugger Pidgin */
		'bpq' => 'CPP ', /* Banda Malay */
		'bqi' => 'LRC ', /* Bakhtiari */
		'bqk' => 'BAD0', /* Banda-Mbrès */
		'br' => 'BRE ', /* Breton */
		'bra' => 'BRI ', /* Braj */
		'brc' => 'CPP ', /* Berbice Creole Dutch */
		'bri' => '', /* Mokpwe != Braj Bhasha */
		'brm' => '', /* Barambu != Burmese */
		'bs' => 'BOS ', /* Bosnian */
		'bsh' => '', /* Kati != Bashkir */
		'btb' => 'BTI ', /* Beti (Cameroon) (retired code) */
		'btd' => 'BTD BTK ', /* Batak Dairi (Pakpak) */
		'bti' => '', /* Burate != Beti */
		'btj' => 'MLY ', /* Bacanese Malay */
		'btm' => 'BTM BTK ', /* Batak Mandailing */
		'bto' => 'BIK ', /* Rinconada Bikol */
		'bts' => 'BTS BTK ', /* Batak Simalungun */
		'btx' => 'BTX BTK ', /* Batak Karo */
		'btz' => 'BTZ BTK ', /* Batak Alas-Kluet */
		'bum' => 'BTI ', /* Bulu (Cameroon) */
		'bve' => 'MLY ', /* Berau Malay */
		'bvu' => 'MLY ', /* Bukit Malay */
		'bwe' => 'KRN ', /* Bwe Karen */
		'bxk' => 'LUH ', /* Bukusu */
		'bxo' => 'CPP ', /* Barikanchi */
		'bxp' => 'BTI ', /* Bebil */
		'bxr' => 'RBU ', /* Russia Buriat */
		'byn' => 'BIL ', /* Bilin */
		'byv' => 'BYV BML ', /* Medumba */
		'bzc' => 'MLG ', /* Southern Betsimisaraka Malagasy */
		'bzj' => 'CPP ', /* Belize Kriol English */
		'bzk' => 'CPP ', /* Nicaragua Creole English */
		'ca' => 'CAT ', /* Catalan */
		'caa' => 'MYN ', /* Chortí */
		'cac' => 'MYN ', /* Chuj */
		'caf' => 'CRR ATH ', /* Southern Carrier */
		'cak' => 'CAK MYN ', /* Kaqchikel */
		'cbk' => 'CBK CPP ', /* Chavacano */
		'cbl' => 'QIN ', /* Bualkhaw Chin */
		'ccl' => 'CPP ', /* Cutchi-Swahili */
		'ccm' => 'CPP ', /* Malaccan Creole Malay */
		'cco' => 'CCHN', /* Comaltepec Chinantec */
		'ccq' => 'ARK ', /* Chaungtha (retired code) */
		'cdo' => 'ZHS ', /* Min Dong Chinese */
		'ce' => 'CHE ', /* Chechen */
		'cek' => 'QIN ', /* Eastern Khumi Chin */
		'cey' => 'QIN ', /* Ekai Chin */
		'cfm' => 'HAL QIN ', /* Halam (Falam Chin) */
		'ch' => 'CHA ', /* Chamorro */
		'chf' => 'MYN ', /* Tabasco Chontal */
		'chg' => '', /* Chagatai != Chaha Gurage */
		'chh' => '', /* Chinook != Chattisgarhi */
		'chj' => 'CCHN', /* Ojitlán Chinantec */
		'chk' => 'CHK0', /* Chuukese */
		'chm' => 'HMA LMA ', /* Mari (Russia) [macrolanguage] */
		'chn' => 'CPP ', /* Chinook jargon */
		'chp' => 'CHP SAY ATH ', /* Chipewyan */
		'chq' => 'CCHN', /* Quiotepec Chinantec */
		'chz' => 'CCHN', /* Ozumacín Chinantec */
		'ciw' => 'OJB ', /* Chippewa */
		'cjy' => 'ZHS ', /* Jinyu Chinese */
		'cka' => 'QIN ', /* Khumi Awa Chin (retired code) */
		'ckb' => 'KUR ', /* Central Kurdish */
		'ckn' => 'QIN ', /* Kaang Chin */
		'cks' => 'CPP ', /* Tayo */
		'ckt' => 'CHK ', /* Chukot */
		'ckz' => 'MYN ', /* Cakchiquel-Quiché Mixed Language */
		'clc' => 'ATH ', /* Chilcotin */
		'cld' => 'SYR ', /* Chaldean Neo-Aramaic */
		'cle' => 'CCHN', /* Lealao Chinantec */
		'clj' => 'QIN ', /* Laitu Chin */
		'cls' => 'SAN ', /* Classical Sanskrit */
		'clt' => 'QIN ', /* Lautu Chin */
		'cmn' => 'ZHS ', /* Mandarin Chinese */
		'cmr' => 'QIN ', /* Mro-Khimi Chin */
		'cnb' => 'QIN ', /* Chinbon Chin */
		'cnh' => 'QIN ', /* Hakha Chin */
		'cnk' => 'QIN ', /* Khumi Chin */
		'cnl' => 'CCHN', /* Lalana Chinantec */
		'cnp' => 'ZHS ', /* Northern Ping Chinese */
		'cnr' => 'SRB ', /* Montenegrin */
		'cnt' => 'CCHN', /* Tepetotutla Chinantec */
		'cnu' => 'BBR ', /* Chenoua */
		'cnw' => 'QIN ', /* Ngawn Chin */
		'co' => 'COS ', /* Corsican */
		'coa' => 'MLY ', /* Cocos Islands Malay */
		'cob' => 'MYN ', /* Chicomuceltec */
		'coq' => 'ATH ', /* Coquille */
		'cpa' => 'CCHN', /* Palantla Chinantec */
		'cpe' => 'CPP ', /* English-based creoles and pidgins [collection] */
		'cpf' => 'CPP ', /* French-based creoles and pidgins [collection] */
		'cpi' => 'CPP ', /* Chinese Pidgin English */
		'cpx' => 'ZHS ', /* Pu-Xian Chinese */
		'cqd' => 'HMN ', /* Chuanqiandian Cluster Miao */
		'cqu' => 'QUH QUZ ', /* Chilean Quechua (retired code) */
		'cr' => 'CRE ', /* Cree [macrolanguage] */
		'crh' => 'CRT ', /* Crimean Tatar */
		'cri' => 'CPP ', /* Sãotomense */
		'crj' => 'ECR YCR CRE ', /* Southern East Cree */
		'crk' => 'WCR YCR CRE ', /* Plains Cree */
		'crl' => 'ECR YCR CRE ', /* Northern East Cree */
		'crm' => 'MCR LCR CRE ', /* Moose Cree */
		'crp' => 'CPP ', /* Creoles and pidgins [collection] */
		'crr' => '', /* Carolina Algonquian != Carrier */
		'crs' => 'CPP ', /* Seselwa Creole French */
		'crt' => '', /* Iyojwa'ja Chorote != Crimean Tatar */
		'crx' => 'CRR ATH ', /* Carrier */
		'cs' => 'CSY ', /* Czech */
		'csa' => 'CCHN', /* Chiltepec Chinantec */
		'csh' => 'QIN ', /* Asho Chin */
		'csj' => 'QIN ', /* Songlai Chin */
		'csl' => '', /* Chinese Sign Language != Church Slavonic */
		'cso' => 'CCHN', /* Sochiapam Chinantec */
		'csp' => 'ZHS ', /* Southern Ping Chinese */
		'csv' => 'QIN ', /* Sumtu Chin */
		'csw' => 'NCR NHC CRE ', /* Swampy Cree */
		'csy' => 'QIN ', /* Siyin Chin */
		'ctc' => 'ATH ', /* Chetco */
		'ctd' => 'QIN ', /* Tedim Chin */
		'cte' => 'CCHN', /* Tepinapa Chinantec */
		'cth' => 'QIN ', /* Thaiphum Chin */
		'ctl' => 'CCHN', /* Tlacoatzintepec Chinantec */
		'cts' => 'BIK ', /* Northern Catanduanes Bikol */
		'ctu' => 'MYN ', /* Chol */
		'cu' => 'CSL ', /* Church Slavonic */
		'cuc' => 'CCHN', /* Usila Chinantec */
		'cv' => 'CHU ', /* Chuvash */
		'cvn' => 'CCHN', /* Valle Nacional Chinantec */
		'cwd' => 'DCR TCR CRE ', /* Woods Cree */
		'cy' => 'WEL ', /* Welsh */
		'czh' => 'ZHS ', /* Huizhou Chinese */
		'czo' => 'ZHS ', /* Min Zhong Chinese */
		'czt' => 'QIN ', /* Zotung Chin */
		'da' => 'DAN ', /* Danish */
		'dao' => 'QIN ', /* Daai Chin */
		'dap' => 'NIS ', /* Nisi (India) (retired code) */
		'dcr' => 'CPP ', /* Negerhollands */
		'de' => 'DEU ', /* German */
		'den' => 'SLA ATH ', /* Slavey [macrolanguage] */
		'dep' => 'CPP ', /* Pidgin Delaware */
		'dgo' => 'DGO DGR ', /* Dogri (individual language) */
		'dgr' => 'ATH ', /* Tlicho */
		'dhd' => 'MAW ', /* Dhundari */
		'dhv' => '', /* Dehu != Divehi (Dhivehi, Maldivian) (deprecated) */
		'dib' => 'DNK ', /* South Central Dinka */
		'dik' => 'DNK ', /* Southwestern Dinka */
		'din' => 'DNK ', /* Dinka [macrolanguage] */
		'dip' => 'DNK ', /* Northeastern Dinka */
		'diq' => 'DIQ ZZA ', /* Dimli */
		'diw' => 'DNK ', /* Northwestern Dinka */
		'dje' => 'DJR ', /* Zarma */
		'djk' => 'CPP ', /* Eastern Maroon Creole */
		'djr' => 'DJR0', /* Djambarrpuyngu */
		'dks' => 'DNK ', /* Southeastern Dinka */
		'dng' => 'DUN ', /* Dungan */
		'dnk' => '', /* Dengka != Dinka */
		'doi' => 'DGR ', /* Dogri (macrolanguage) [macrolanguage] */
		'drh' => 'MNG ', /* Darkhat (retired code) */
		'dri' => '', /* C'Lela != Dari */
		'drw' => 'DRI FAR ', /* Darwazi (retired code) */
		'dsb' => 'LSB ', /* Lower Sorbian */
		'dty' => 'NEP ', /* Dotyali */
		'dun' => '', /* Dusun Deyah != Dungan */
		'dup' => 'MLY ', /* Duano */
		'dv' => 'DIV DHV ', /* Divehi (Dhivehi, Maldivian) */
		'dwk' => 'KUI ', /* Dawik Kui */
		'dwu' => 'DUJ ', /* Dhuwal */
		'dwy' => 'DUJ ', /* Dhuwaya */
		'dyu' => 'JUL ', /* Dyula */
		'dz' => 'DZN ', /* Dzongkha */
		'dzn' => '', /* Dzando != Dzongkha */
		'ecr' => '', /* Eteocretan != Eastern Cree */
		'ee' => 'EWE ', /* Ewe */
		'ekk' => 'ETI ', /* Standard Estonian */
		'eky' => 'KRN ', /* Eastern Kayah */
		'el' => 'ELL ', /* Modern Greek (1453-) */
		'emk' => 'EMK MNK ', /* Eastern Maninkakan */
		'emy' => 'MYN ', /* Epigraphic Mayan */
		'en' => 'ENG ', /* English */
		'enb' => 'KAL ', /* Markweeta */
		'enf' => 'FNE ', /* Forest Enets */
		'enh' => 'TNE ', /* Tundra Enets */
		'eo' => 'NTO ', /* Esperanto */
		'es' => 'ESP ', /* Spanish */
		'esg' => 'GON ', /* Aheri Gondi */
		'esi' => 'IPK ', /* North Alaskan Inupiatun */
		'esk' => 'IPK ', /* Northwest Alaska Inupiatun */
		'et' => 'ETI ', /* Estonian [macrolanguage] */
		'eto' => 'BTI ', /* Eton (Cameroon) */
		'eu' => 'EUQ ', /* Basque */
		'euq' => '', /* Basque [collection] != Basque */
		'eve' => 'EVN ', /* Even */
		'evn' => 'EVK ', /* Evenki */
		'ewo' => 'BTI ', /* Ewondo */
		'eyo' => 'KAL ', /* Keiyo */
		'fa' => 'FAR ', /* Persian [macrolanguage] */
		'fab' => 'CPP ', /* Fa d'Ambu */
		'fan' => 'FAN0BTI ', /* Fang (Equatorial Guinea) */
		'far' => '', /* Fataleka != Persian */
		'fat' => 'FAT AKA ', /* Fanti */
		'fbl' => 'BIK ', /* West Albay Bikol */
		'ff' => 'FUL ', /* Fulah [macrolanguage] */
		'ffm' => 'FUL ', /* Maasina Fulfulde */
		'fi' => 'FIN ', /* Finnish */
		'fil' => 'PIL ', /* Filipino */
		'fj' => 'FJI ', /* Fijian */
		'flm' => 'HAL QIN ', /* Halam (Falam Chin) (retired code) */
		'fmp' => 'FMP BML ', /* Fe’fe’ */
		'fng' => 'CPP ', /* Fanagalo */
		'fo' => 'FOS ', /* Faroese */
		'fos' => '', /* Siraya != Faroese */
		'fpe' => 'CPP ', /* Fernando Po Creole English */
		'fr' => 'FRA ', /* French */
		'fub' => 'FUL ', /* Adamawa Fulfulde */
		'fuc' => 'FUL ', /* Pulaar */
		'fue' => 'FUL ', /* Borgu Fulfulde */
		'fuf' => 'FTA FUL ', /* Pular */
		'fuh' => 'FUL ', /* Western Niger Fulfulde */
		'fui' => 'FUL ', /* Bagirmi Fulfulde */
		'fuq' => 'FUL ', /* Central-Eastern Niger Fulfulde */
		'fur' => 'FRL ', /* Friulian */
		'fuv' => 'FUV FUL ', /* Nigerian Fulfulde */
		'fy' => 'FRI ', /* Western Frisian */
		'ga' => 'IRI IRT ', /* Irish */
		'gaa' => 'GAD ', /* Ga */
		'gac' => 'CPP ', /* Mixed Great Andamanese */
		'gad' => '', /* Gaddang != Ga */
		'gae' => '', /* Guarequena != Scottish Gaelic */
		'gal' => '', /* Galolen != Galician */
		'gan' => 'ZHS ', /* Gan Chinese */
		'gar' => '', /* Galeya != Garshuni */
		'gaw' => '', /* Nobonob != Garhwali */
		'gax' => 'ORO ', /* Borana-Arsi-Guji Oromo */
		'gaz' => 'ORO ', /* West Central Oromo */
		'gbm' => 'GAW ', /* Garhwali */
		'gce' => 'ATH ', /* Galice */
		'gcf' => 'CPP ', /* Guadeloupean Creole French */
		'gcl' => 'CPP ', /* Grenadian Creole English */
		'gcr' => 'CPP ', /* Guianese Creole French */
		'gd' => 'GAE ', /* Scottish Gaelic */
		'gda' => 'RAJ ', /* Gade Lohar */
		'ggo' => 'GON ', /* Southern Gondi (retired code) */
		'gha' => 'BBR ', /* Ghadamès */
		'ghc' => 'IRT ', /* Hiberno-Scottish Gaelic */
		'ghk' => 'KRN ', /* Geko Karen */
		'gho' => 'BBR ', /* Ghomara */
		'gib' => 'CPP ', /* Gibanawa */
		'gil' => 'GIL0', /* Kiribati (Gilbertese) */
		'gju' => 'RAJ ', /* Gujari */
		'gkp' => 'GKP KPL ', /* Guinea Kpelle */
		'gl' => 'GAL ', /* Galician */
		'gld' => 'NAN ', /* Nanai */
		'gmz' => '', /* Mgbolizhia != Gumuz */
		'gn' => 'GUA ', /* Guarani [macrolanguage] */
		'gnb' => 'QIN ', /* Gangte */
		'gno' => 'GON ', /* Northern Gondi */
		'gnw' => 'GUA ', /* Western Bolivian Guaraní */
		'gom' => 'KOK ', /* Goan Konkani */
		'goq' => 'CPP ', /* Gorap */
		'gox' => 'BAD0', /* Gobu */
		'gpe' => 'CPP ', /* Ghanaian Pidgin English */
		'gro' => '', /* Groma != Garo */
		'grr' => 'BBR ', /* Taznatit */
		'grt' => 'GRO ', /* Garo */
		'gru' => 'SOG ', /* Kistane */
		'gsw' => 'ALS ', /* Alsatian */
		'gu' => 'GUJ ', /* Gujarati */
		'gua' => '', /* Shiki != Guarani */
		'gug' => 'GUA ', /* Paraguayan Guaraní */
		'gui' => 'GUA ', /* Eastern Bolivian Guaraní */
		'guk' => 'GMZ ', /* Gumuz */
		'gul' => 'CPP ', /* Sea Island Creole English */
		'gun' => 'GUA ', /* Mbyá Guaraní */
		'gv' => 'MNX ', /* Manx */
		'gwi' => 'ATH ', /* Gwichʼin */
		'gyn' => 'CPP ', /* Guyanese Creole English */
		'ha' => 'HAU ', /* Hausa */
		'haa' => 'ATH ', /* Hän */
		'hae' => 'ORO ', /* Eastern Oromo */
		'hai' => 'HAI0', /* Haida [macrolanguage] */
		'hak' => 'ZHS ', /* Hakka Chinese */
		'hal' => '', /* Halang != Halam (Falam Chin) */
		'har' => 'HRI ', /* Harari */
		'hax' => 'HAI0', /* Southern Haida */
		'hbn' => '', /* Heiban != Hammer-Banna */
		'hca' => 'CPP ', /* Andaman Creole Hindi */
		'hdn' => 'HAI0', /* Northern Haida */
		'he' => 'IWR ', /* Hebrew */
		'hea' => 'HMN ', /* Northern Qiandong Miao */
		'hi' => 'HIN ', /* Hindi */
		'hji' => 'MLY ', /* Haji */
		'hlt' => 'QIN ', /* Matu Chin */
		'hma' => 'HMN ', /* Southern Mashan Hmong */
		'hmc' => 'HMN ', /* Central Huishui Hmong */
		'hmd' => 'HMD HMN ', /* Large Flowery Miao */
		'hme' => 'HMN ', /* Eastern Huishui Hmong */
		'hmg' => 'HMN ', /* Southwestern Guiyang Hmong */
		'hmh' => 'HMN ', /* Southwestern Huishui Hmong */
		'hmi' => 'HMN ', /* Northern Huishui Hmong */
		'hmj' => 'HMN ', /* Ge */
		'hml' => 'HMN ', /* Luopohe Hmong */
		'hmm' => 'HMN ', /* Central Mashan Hmong */
		'hmp' => 'HMN ', /* Northern Mashan Hmong */
		'hmq' => 'HMN ', /* Eastern Qiandong Miao */
		'hmr' => 'QIN ', /* Hmar */
		'hms' => 'HMN ', /* Southern Qiandong Miao */
		'hmw' => 'HMN ', /* Western Mashan Hmong */
		'hmy' => 'HMN ', /* Southern Guiyang Hmong */
		'hmz' => 'HMZ HMN ', /* Hmong Shua */
		'hne' => 'CHH ', /* Chhattisgarhi */
		'hnj' => 'HMN ', /* Hmong Njua */
		'hnm' => 'ZHS ', /* Hainanese */
		'hno' => 'HND ', /* Northern Hindko */
		'ho' => 'HMO CPP ', /* Hiri Motu */
		'hoc' => 'HO  ', /* Ho */
		'hoi' => 'ATH ', /* Holikachuk */
		'hoj' => 'HAR RAJ ', /* Hadothi */
		'hr' => 'HRV ', /* Croatian */
		'hra' => 'QIN ', /* Hrangkhol */
		'hrm' => 'HMN ', /* Horned Miao */
		'hsb' => 'USB ', /* Upper Sorbian */
		'hsn' => 'ZHS ', /* Xiang Chinese */
		'ht' => 'HAI CPP ', /* Haitian (Haitian Creole) */
		'hu' => 'HUN ', /* Hungarian */
		'huj' => 'HMN ', /* Northern Guiyang Hmong */
		'hup' => 'ATH ', /* Hupa */
		'hus' => 'MYN ', /* Huastec */
		'hwc' => 'CPP ', /* Hawai'i Creole English */
		'hy' => 'HYE0HYE ', /* Armenian */
		'hyw' => 'HYE ', /* Western Armenian */
		'hz' => 'HER ', /* Herero */
		'ia' => 'INA ', /* Interlingua (IALA) */
		'iby' => 'IJO ', /* Ibani */
		'icr' => 'CPP ', /* Islander Creole English */
		'id' => 'IND MLY ', /* Indonesian */
		'ida' => 'LUH ', /* Idakho-Isukha-Tiriki */
		'idb' => 'CPP ', /* Indo-Portuguese */
		'ie' => 'ILE ', /* Interlingue */
		'ig' => 'IBO ', /* Igbo */
		'igb' => 'EBI ', /* Ebira */
		'ihb' => 'CPP ', /* Iha Based Pidgin */
		'ii' => 'YIM ', /* Sichuan Yi */
		'ijc' => 'IJO ', /* Izon */
		'ije' => 'IJO ', /* Biseni */
		'ijn' => 'IJO ', /* Kalabari */
		'ijs' => 'IJO ', /* Southeast Ijo */
		'ik' => 'IPK ', /* Inupiaq [macrolanguage] */
		'ike' => 'INU INUK', /* Eastern Canadian Inuktitut */
		'ikt' => 'INU ', /* Inuinnaqtun */
		'in' => 'IND MLY ', /* Indonesian (retired code) */
		'ing' => 'ATH ', /* Degexit'an */
		'inh' => 'ING ', /* Ingush */
		'io' => 'IDO ', /* Ido */
		'iri' => '', /* Rigwe != Irish */
		'is' => 'ISL ', /* Icelandic */
		'ism' => '', /* Masimasi != Inari Sami */
		'it' => 'ITA ', /* Italian */
		'itz' => 'MYN ', /* Itzá */
		'iu' => 'INU INUK', /* Inuktitut [macrolanguage] */
		'iw' => 'IWR ', /* Hebrew (retired code) */
		'ixl' => 'MYN ', /* Ixil */
		'ja' => 'JAN ', /* Japanese */
		'jac' => 'MYN ', /* Popti' */
		'jak' => 'MLY ', /* Jakun */
		'jam' => 'JAM CPP ', /* Jamaican Creole English */
		'jan' => '', /* Jandai != Japanese */
		'jax' => 'MLY ', /* Jambi Malay */
		'jbe' => 'BBR ', /* Judeo-Berber */
		'jbn' => 'BBR ', /* Nafusi */
		'jgo' => 'BML ', /* Ngomba */
		'ji' => 'JII ', /* Yiddish (retired code) */
		'jii' => '', /* Jiiddu != Yiddish */
		'jkm' => 'KRN ', /* Mobwa Karen */
		'jkp' => 'KRN ', /* Paku Karen */
		'jud' => '', /* Worodougou != Ladino */
		'jul' => '', /* Jirel != Jula */
		'jv' => 'JAV ', /* Javanese */
		'jvd' => 'CPP ', /* Javindo */
		'jw' => 'JAV ', /* Javanese (retired code) */
		'ka' => 'KAT ', /* Georgian */
		'kaa' => 'KRK ', /* Karakalpak */
		'kab' => 'KAB0BBR ', /* Kabyle */
		'kac' => '', /* Kachin != Kachchi */
		'kam' => 'KMB ', /* Kamba (Kenya) */
		'kar' => 'KRN ', /* Karen [collection] */
		'kbd' => 'KAB ', /* Kabardian */
		'kby' => 'KNR ', /* Manga Kanuri */
		'kca' => 'KHK KHS KHV ', /* Khanty */
		'kcn' => 'CPP ', /* Nubi */
		'kdr' => 'KRM ', /* Karaim */
		'kdt' => 'KUY ', /* Kuy */
		'kea' => 'KEA CPP ', /* Kabuverdianu (Crioulo) */
		'keb' => '', /* Kélé != Kebena */
		'kek' => 'KEK MYN ', /* Kekchi */
		'kex' => 'KKN ', /* Kukna */
		'kfa' => 'KOD ', /* Kodava */
		'kfr' => 'KAC ', /* Kachhi */
		'kfx' => 'KUL ', /* Kullu Pahari */
		'kfy' => 'KMN ', /* Kumaoni */
		'kg' => 'KON0', /* Kongo [macrolanguage] */
		'kge' => '', /* Komering != Khutsuri Georgian */
		'kha' => 'KSI ', /* Khasi */
		'khb' => 'XBD ', /* Lü */
		'khk' => 'MNG ', /* Halh Mongolian */
		'khn' => '', /* Khandesi != Khamti Shan (Microsoft fonts) */
		'khs' => '', /* Kasua != Khanty-Shurishkar */
		'kht' => 'KHT KHN ', /* Khamti */
		'khv' => '', /* Khvarshi != Khanty-Vakhi */
		'ki' => 'KIK ', /* Kikuyu (Gikuyu) */
		'kis' => '', /* Kis != Kisii */
		'kiu' => 'KIU ZZA ', /* Kirmanjki */
		'kj' => 'KUA ', /* Kuanyama */
		'kjb' => 'MYN ', /* Q'anjob'al */
		'kjh' => 'KHA ', /* Khakas */
		'kjp' => 'KJP KRN ', /* Pwo Eastern Karen */
		'kjt' => 'KRN ', /* Phrae Pwo Karen */
		'kk' => 'KAZ ', /* Kazakh */
		'kkn' => '', /* Kon Keu != Kokni */
		'kkz' => 'ATH ', /* Kaska */
		'kl' => 'GRN ', /* Greenlandic */
		'klm' => '', /* Migum != Kalmyk */
		'kln' => 'KAL ', /* Kalenjin [macrolanguage] */
		'km' => 'KHM ', /* Khmer */
		'kmb' => 'MBN ', /* Kimbundu */
		'kmn' => '', /* Awtuw != Kumaoni */
		'kmo' => '', /* Kwoma != Komo */
		'kmr' => 'KUR ', /* Northern Kurdish */
		'kms' => '', /* Kamasau != Komso */
		'kmv' => 'CPP ', /* Karipúna Creole French */
		'kmw' => 'KMO ', /* Komo (Democratic Republic of Congo) */
		'kn' => 'KAN ', /* Kannada */
		'knc' => 'KNR ', /* Central Kanuri */
		'kng' => 'KON0', /* Koongo */
		'knj' => 'MYN ', /* Western Kanjobal */
		'knn' => 'KOK ', /* Konkani */
		'knr' => '', /* Kaningra != Kanuri */
		'ko' => 'KOR KOH ', /* Korean */
		'kod' => '', /* Kodi != Kodagu */
		'koh' => '', /* Koyo != Korean Old Hangul */
		'koi' => 'KOP KOM ', /* Komi-Permyak */
		'kop' => '', /* Waube != Komi-Permyak */
		'koy' => 'ATH ', /* Koyukon */
		'koz' => '', /* Korak != Komi-Zyrian */
		'kpe' => 'KPL ', /* Kpelle [macrolanguage] */
		'kpl' => '', /* Kpala != Kpelle */
		'kpp' => 'KRN ', /* Paku Karen (retired code) */
		'kpv' => 'KOZ KOM ', /* Komi-Zyrian */
		'kpy' => 'KYK ', /* Koryak */
		'kqs' => 'KIS ', /* Northern Kissi */
		'kqy' => 'KRT ', /* Koorete */
		'kr' => 'KNR ', /* Kanuri [macrolanguage] */
		'krc' => 'KAR BAL ', /* Karachay-Balkar */
		'kri' => 'KRI CPP ', /* Krio */
		'krk' => '', /* Kerek != Karakalpak */
		'krm' => '', /* Krim (retired code) != Karaim */
		'krn' => '', /* Sapo != Karen */
		'krt' => 'KNR ', /* Tumari Kanuri */
		'kru' => 'KUU ', /* Kurukh */
		'ks' => 'KSH ', /* Kashmiri */
		'ksh' => 'KSH0', /* Kölsch */
		'ksi' => '', /* Krisa != Khasi */
		'ksm' => '', /* Kumba != Kildin Sami */
		'kss' => 'KIS ', /* Southern Kisi */
		'ksw' => 'KSW KRN ', /* S’gaw Karen */
		'ktb' => 'KEB ', /* Kambaata */
		'ktu' => 'KON ', /* Kituba (Democratic Republic of Congo) */
		'ktw' => 'ATH ', /* Kato */
		'ku' => 'KUR ', /* Kurdish [macrolanguage] */
		'kui' => '', /* Kuikúro-Kalapálo != Kui */
		'kul' => '', /* Kulere != Kulvi */
		'kuu' => 'ATH ', /* Upper Kuskokwim */
		'kuw' => 'BAD0', /* Kpagua */
		'kuy' => '', /* Kuuku-Ya'u != Kuy */
		'kv' => 'KOM ', /* Komi [macrolanguage] */
		'kvb' => 'MLY ', /* Kubu */
		'kvl' => 'KRN ', /* Kayaw */
		'kvq' => 'KVQ KRN ', /* Geba Karen */
		'kvr' => 'MLY ', /* Kerinci */
		'kvt' => 'KRN ', /* Lahta Karen */
		'kvu' => 'KRN ', /* Yinbaw Karen */
		'kvy' => 'KRN ', /* Yintale Karen */
		'kw' => 'COR ', /* Cornish */
		'kww' => 'CPP ', /* Kwinti */
		'kwy' => 'KON0', /* San Salvador Kongo */
		'kxc' => 'KMS ', /* Konso */
		'kxd' => 'MLY ', /* Brunei */
		'kxf' => 'KRN ', /* Manumanaw Karen */
		'kxk' => 'KRN ', /* Zayein Karen */
		'kxl' => 'KUU ', /* Nepali Kurux (retired code) */
		'kxu' => 'KUI ', /* Kui (India) (retired code) */
		'ky' => 'KIR ', /* Kirghiz (Kyrgyz) */
		'kyk' => '', /* Kamayo != Koryak */
		'kyu' => 'KYU KRN ', /* Western Kayah */
		'la' => 'LAT ', /* Latin */
		'lac' => 'MYN ', /* Lacandon */
		'lad' => 'JUD ', /* Ladino */
		'lah' => '', /* Lahnda [macrolanguage] != Lahuli */
		'lak' => '', /* Laka (Nigeria) (retired code) != Lak */
		'lam' => '', /* Lamba != Lambani */
		'laz' => '', /* Aribwatsa != Laz */
		'lb' => 'LTZ ', /* Luxembourgish */
		'lbe' => 'LAK ', /* Lak */
		'lbj' => 'LDK ', /* Ladakhi */
		'lbl' => 'BIK ', /* Libon Bikol */
		'lce' => 'MLY ', /* Loncong */
		'lcf' => 'MLY ', /* Lubu */
		'ldi' => 'KON0', /* Laari */
		'ldk' => '', /* Leelau != Ladakhi */
		'lg' => 'LUG ', /* Ganda */
		'li' => 'LIM ', /* Limburgish */
		'lif' => 'LMB ', /* Limbu */
		'lir' => 'CPP ', /* Liberian English */
		'liw' => 'MLY ', /* Col */
		'liy' => 'BAD0', /* Banda-Bambari */
		'lkb' => 'LUH ', /* Kabras */
		'lko' => 'LUH ', /* Khayo */
		'lks' => 'LUH ', /* Kisa */
		'lld' => 'LAD ', /* Ladin */
		'lma' => '', /* East Limba != Low Mari */
		'lmb' => '', /* Merei != Limbu */
		'lmn' => 'LAM ', /* Lambadi */
		'lmw' => '', /* Lake Miwok != Lomwe */
		'ln' => 'LIN ', /* Lingala */
		'lna' => 'BAD0', /* Langbashe */
		'lnl' => 'BAD0', /* South Central Banda */
		'lo' => 'LAO ', /* Lao */
		'lou' => 'CPP ', /* Louisiana Creole */
		'lri' => 'LUH ', /* Marachi */
		'lrm' => 'LUH ', /* Marama */
		'lrt' => 'CPP ', /* Larantuka Malay */
		'lsb' => '', /* Burundian Sign Language != Lower Sorbian */
		'lsm' => 'LUH ', /* Saamia */
		'lt' => 'LTH ', /* Lithuanian */
		'ltg' => 'LVI ', /* Latgalian */
		'lth' => '', /* Thur != Lithuanian */
		'lto' => 'LUH ', /* Tsotso */
		'lts' => 'LUH ', /* Tachoni */
		'lu' => 'LUB ', /* Luba-Katanga */
		'luh' => 'ZHS ', /* Leizhou Chinese */
		'lus' => 'MIZ QIN ', /* Lushai */
		'luy' => 'LUH ', /* Luyia [macrolanguage] */
		'luz' => 'LRC ', /* Southern Luri */
		'lv' => 'LVI ', /* Latvian [macrolanguage] */
		'lvi' => '', /* Lavi != Latvian */
		'lvs' => 'LVI ', /* Standard Latvian */
		'lwg' => 'LUH ', /* Wanga */
		'lzh' => 'ZHT ', /* Literary Chinese */
		'lzz' => 'LAZ ', /* Laz */
		'mai' => 'MTH ', /* Maithili */
		'maj' => '', /* Jalapa De Díaz Mazatec != Majang */
		'mak' => 'MKR ', /* Makasar */
		'mam' => 'MAM MYN ', /* Mam */
		'man' => 'MNK ', /* Mandingo [macrolanguage] */
		'map' => '', /* Austronesian [collection] != Mapudungun */
		'maw' => '', /* Mampruli != Marwari */
		'max' => 'MLY CPP ', /* North Moluccan Malay */
		'mbf' => 'CPP ', /* Baba Malay */
		'mbn' => '', /* Macaguán != Mbundu */
		'mch' => '', /* Maquiritari != Manchu */
		'mcm' => 'CPP ', /* Malaccan Creole Portuguese */
		'mcr' => '', /* Menya != Moose Cree */
		'mct' => 'BTI ', /* Mengisa */
		'mde' => '', /* Maba (Chad) != Mende */
		'mdf' => 'MOK ', /* Moksha */
		'mdy' => 'MLE ', /* Male */
		'men' => 'MDE ', /* Mende (Sierra Leone) */
		'meo' => 'MLY ', /* Kedah Malay */
		'mfa' => 'MFA MLY ', /* Pattani Malay */
		'mfb' => 'MLY ', /* Bangka */
		'mfe' => 'MFE CPP ', /* Morisyen */
		'mfp' => 'CPP ', /* Makassar Malay */
		'mg' => 'MLG ', /* Malagasy [macrolanguage] */
		'mga' => 'SGA ', /* Middle Irish (900-1200) */
		'mh' => 'MAH ', /* Marshallese */
		'mhc' => 'MYN ', /* Mocho */
		'mhr' => 'LMA ', /* Eastern Mari */
		'mhv' => 'ARK ', /* Arakanese (retired code) */
		'mi' => 'MRI ', /* Maori */
		'min' => 'MIN MLY ', /* Minangkabau */
		'miz' => '', /* Coatzospan Mixtec != Mizo */
		'mk' => 'MKD ', /* Macedonian */
		'mkn' => 'CPP ', /* Kupang Malay */
		'mkr' => '', /* Malas != Makasar */
		'mku' => 'MNK ', /* Konyanka Maninka */
		'ml' => 'MAL MLR ', /* Malayalam */
		'mle' => '', /* Manambu != Male */
		'mln' => '', /* Malango != Malinke */
		'mlq' => 'MLN MNK ', /* Western Maninkakan */
		'mlr' => '', /* Vame != Malayalam Reformed */
		'mmr' => 'HMN ', /* Western Xiangxi Miao */
		'mn' => 'MNG ', /* Mongolian [macrolanguage] */
		'mnc' => 'MCH ', /* Manchu */
		'mnd' => '', /* Mondé != Mandinka */
		'mng' => '', /* Eastern Mnong != Mongolian */
		'mnh' => 'BAD0', /* Mono (Democratic Republic of Congo) */
		'mnk' => 'MND MNK ', /* Mandinka */
		'mnp' => 'ZHS ', /* Min Bei Chinese */
		'mns' => 'MAN ', /* Mansi */
		'mnw' => 'MON MONT', /* Mon */
		'mnx' => '', /* Manikion != Manx */
		'mo' => 'MOL ROM ', /* Moldavian (retired code) */
		'mod' => 'CPP ', /* Mobilian */
		'mok' => '', /* Morori != Moksha */
		'mop' => 'MYN ', /* Mopán Maya */
		'mor' => '', /* Moro != Moroccan */
		'mpe' => 'MAJ ', /* Majang */
		'mqg' => 'MLY ', /* Kota Bangun Kutai Malay */
		'mr' => 'MAR ', /* Marathi */
		'mrh' => 'QIN ', /* Mara Chin */
		'mrj' => 'HMA ', /* Western Mari */
		'ms' => 'MLY ', /* Malay [macrolanguage] */
		'msc' => 'MNK ', /* Sankaran Maninka */
		'msh' => 'MLG ', /* Masikoro Malagasy */
		'msi' => 'MLY CPP ', /* Sabah Malay */
		'mt' => 'MTS ', /* Maltese */
		'mth' => '', /* Munggui != Maithili */
		'mtr' => 'MAW ', /* Mewari */
		'mts' => '', /* Yora != Maltese */
		'mud' => 'CPP ', /* Mednyj Aleut */
		'mui' => 'MLY ', /* Musi */
		'mun' => '', /* Munda [collection] != Mundari */
		'mup' => 'RAJ ', /* Malvi */
		'muq' => 'HMN ', /* Eastern Xiangxi Miao */
		'mvb' => 'ATH ', /* Mattole */
		'mve' => 'MAW ', /* Marwari (Pakistan) */
		'mvf' => 'MNG ', /* Peripheral Mongolian */
		'mwk' => 'MNK ', /* Kita Maninkakan */
		'mwq' => 'QIN ', /* Mün Chin */
		'mwr' => 'MAW ', /* Marwari [macrolanguage] */
		'mww' => 'MWW HMN ', /* Hmong Daw */
		'my' => 'BRM ', /* Burmese */
		'mym' => 'MEN ', /* Me’en */
		'myq' => 'MNK ', /* Forest Maninka (retired code) */
		'myv' => 'ERZ ', /* Erzya */
		'mzb' => 'BBR ', /* Tumzabt */
		'mzs' => 'CPP ', /* Macanese */
		'na' => 'NAU ', /* Nauru */
		'nag' => 'NAG CPP ', /* Naga Pidgin */
		'nan' => 'ZHS ', /* Min Nan Chinese */
		'nas' => '', /* Naasioi != Naskapi */
		'naz' => 'NAH ', /* Coatepec Nahuatl */
		'nb' => 'NOR ', /* Norwegian Bokmål */
		'nch' => 'NAH ', /* Central Huasteca Nahuatl */
		'nci' => 'NAH ', /* Classical Nahuatl */
		'ncj' => 'NAH ', /* Northern Puebla Nahuatl */
		'ncl' => 'NAH ', /* Michoacán Nahuatl */
		'ncr' => '', /* Ncane != N-Cree */
		'ncx' => 'NAH ', /* Central Puebla Nahuatl */
		'nd' => 'NDB ', /* North Ndebele */
		'ndb' => '', /* Kenswei Nsei != Ndebele */
		'ndg' => '', /* Ndengereko != Ndonga */
		'ne' => 'NEP ', /* Nepali [macrolanguage] */
		'nef' => 'CPP ', /* Nefamese */
		'ng' => 'NDG ', /* Ndonga */
		'ngl' => 'LMW ', /* Lomwe */
		'ngm' => 'CPP ', /* Ngatik Men's Creole */
		'ngo' => 'SXT ', /* Ngoni (retired code) */
		'ngr' => '', /* Engdewu != Nagari */
		'ngu' => 'NAH ', /* Guerrero Nahuatl */
		'nhc' => 'NAH ', /* Tabasco Nahuatl */
		'nhd' => 'GUA ', /* Chiripá */
		'nhe' => 'NAH ', /* Eastern Huasteca Nahuatl */
		'nhg' => 'NAH ', /* Tetelcingo Nahuatl */
		'nhi' => 'NAH ', /* Zacatlán-Ahuacatlán-Tepetzintla Nahuatl */
		'nhk' => 'NAH ', /* Isthmus-Cosoleacaque Nahuatl */
		'nhm' => 'NAH ', /* Morelos Nahuatl */
		'nhn' => 'NAH ', /* Central Nahuatl */
		'nhp' => 'NAH ', /* Isthmus-Pajapan Nahuatl */
		'nhq' => 'NAH ', /* Huaxcaleca Nahuatl */
		'nht' => 'NAH ', /* Ometepec Nahuatl */
		'nhv' => 'NAH ', /* Temascaltepec Nahuatl */
		'nhw' => 'NAH ', /* Western Huasteca Nahuatl */
		'nhx' => 'NAH ', /* Isthmus-Mecayapan Nahuatl */
		'nhy' => 'NAH ', /* Northern Oaxaca Nahuatl */
		'nhz' => 'NAH ', /* Santa María La Alta Nahuatl */
		'niq' => 'KAL ', /* Nandi */
		'nis' => '', /* Nimi != Nisi */
		'niv' => 'GIL ', /* Gilyak */
		'njt' => 'CPP ', /* Ndyuka-Trio Pidgin */
		'njz' => 'NIS ', /* Nyishi */
		'nko' => '', /* Nkonya != N’Ko */
		'nkx' => 'IJO ', /* Nkoroo */
		'nl' => 'NLD ', /* Dutch */
		'nla' => 'BML ', /* Ngombale */
		'nle' => 'LUH ', /* East Nyala */
		'nln' => 'NAH ', /* Durango Nahuatl (retired code) */
		'nlv' => 'NAH ', /* Orizaba Nahuatl */
		'nn' => 'NYN ', /* Norwegian Nynorsk (Nynorsk, Norwegian) */
		'nnh' => 'BML ', /* Ngiemboon */
		'nnz' => 'BML ', /* Nda'nda' */
		'no' => 'NOR ', /* Norwegian [macrolanguage] */
		'nod' => 'NTA ', /* Northern Thai */
		'npi' => 'NEP ', /* Nepali */
		'npl' => 'NAH ', /* Southeastern Puebla Nahuatl */
		'nqo' => 'NKO ', /* N’Ko */
		'nr' => 'NDB ', /* South Ndebele */
		'nsk' => 'NAS ', /* Naskapi */
		'nsm' => '', /* Sumi Naga != Northern Sami */
		'nsu' => 'NAH ', /* Sierra Negra Nahuatl */
		'nto' => '', /* Ntomba != Esperanto */
		'nue' => 'BAD0', /* Ngundu */
		'nuu' => 'BAD0', /* Ngbundu */
		'nuz' => 'NAH ', /* Tlamacazapa Nahuatl */
		'nv' => 'NAV ATH ', /* Navajo */
		'nwe' => 'BML ', /* Ngwe */
		'ny' => 'CHI ', /* Chichewa (Chewa, Nyanja) */
		'nyd' => 'LUH ', /* Nyore */
		'nyn' => 'NKL ', /* Nyankole */
		'oc' => 'OCI ', /* Occitan (post 1500) */
		'oj' => 'OJB ', /* Ojibwa [macrolanguage] */
		'ojc' => 'OJB ', /* Central Ojibwa */
		'ojg' => 'OJB ', /* Eastern Ojibwa */
		'ojs' => 'OCR OJB ', /* Severn Ojibwa */
		'ojw' => 'OJB ', /* Western Ojibwa */
		'okd' => 'IJO ', /* Okodia */
		'oki' => 'KAL ', /* Okiek */
		'okm' => 'KOH ', /* Middle Korean (10th-16th cent.) */
		'okr' => 'IJO ', /* Kirike */
		'om' => 'ORO ', /* Oromo [macrolanguage] */
		'onx' => 'CPP ', /* Onin Based Pidgin */
		'oor' => 'CPP ', /* Oorlams */
		'or' => 'ORI ', /* Odia [macrolanguage] */
		'orc' => 'ORO ', /* Orma */
		'orn' => 'MLY ', /* Orang Kanaq */
		'oro' => '', /* Orokolo != Oromo */
		'orr' => 'IJO ', /* Oruma */
		'ors' => 'MLY ', /* Orang Seletar */
		'ory' => 'ORI ', /* Odia */
		'os' => 'OSS ', /* Ossetian */
		'otw' => 'OJB ', /* Ottawa */
		'oua' => 'BBR ', /* Tagargrent */
		'pa' => 'PAN ', /* Punjabi */
		'paa' => '', /* Papuan [collection] != Palestinian Aramaic */
		'pal' => '', /* Pahlavi != Pali */
		'pap' => 'PAP0CPP ', /* Papiamento */
		'pas' => '', /* Papasena != Pashto */
		'pbt' => 'PAS ', /* Southern Pashto */
		'pbu' => 'PAS ', /* Northern Pashto */
		'pce' => 'PLG ', /* Ruching Palaung */
		'pck' => 'QIN ', /* Paite Chin */
		'pcm' => 'CPP ', /* Nigerian Pidgin */
		'pdu' => 'KRN ', /* Kayan */
		'pea' => 'CPP ', /* Peranakan Indonesian */
		'pel' => 'MLY ', /* Pekal */
		'pes' => 'FAR ', /* Iranian Persian */
		'pey' => 'CPP ', /* Petjo */
		'pga' => 'ARA CPP ', /* Sudanese Creole Arabic */
		'pi' => 'PAL ', /* Pali */
		'pih' => 'PIH CPP ', /* Pitcairn-Norfolk */
		'pil' => '', /* Yom != Filipino */
		'pis' => 'CPP ', /* Pijin */
		'pkh' => 'QIN ', /* Pankhu */
		'pko' => 'KAL ', /* Pökoot */
		'pl' => 'PLK ', /* Polish */
		'plg' => 'PLG0', /* Pilagá */
		'plk' => '', /* Kohistani Shina != Polish */
		'pll' => 'PLG ', /* Shwe Palaung */
		'pln' => 'CPP ', /* Palenquero */
		'plp' => 'PAP ', /* Palpa (retired code) */
		'plt' => 'MLG ', /* Plateau Malagasy */
		'pml' => 'CPP ', /* Lingua Franca */
		'pmy' => 'CPP ', /* Papuan Malay */
		'poc' => 'MYN ', /* Poqomam */
		'poh' => 'POH MYN ', /* Poqomchi' */
		'pov' => 'CPP ', /* Upper Guinea Crioulo */
		'ppa' => 'BAG ', /* Pao (retired code) */
		'pre' => 'CPP ', /* Principense */
		'prp' => 'GUJ ', /* Parsi (retired code) */
		'prs' => 'DRI FAR ', /* Dari */
		'ps' => 'PAS ', /* Pashto [macrolanguage] */
		'pse' => 'MLY ', /* Central Malay */
		'pst' => 'PAS ', /* Central Pashto */
		'pt' => 'PTG ', /* Portuguese */
		'pub' => 'QIN ', /* Purum */
		'puz' => 'QIN ', /* Purum Naga (retired code) */
		'pwo' => 'PWO KRN ', /* Pwo Western Karen */
		'pww' => 'KRN ', /* Pwo Northern Karen */
		'qu' => 'QUZ ', /* Quechua [macrolanguage] */
		'qub' => 'QWH QUZ ', /* Huallaga Huánuco Quechua */
		'quc' => 'QUC MYN ', /* K’iche’ */
		'qud' => 'QVI QUZ ', /* Calderón Highland Quichua */
		'quf' => 'QUZ ', /* Lambayeque Quechua */
		'qug' => 'QVI QUZ ', /* Chimborazo Highland Quichua */
		'quh' => 'QUH QUZ ', /* South Bolivian Quechua */
		'quk' => 'QUZ ', /* Chachapoyas Quechua */
		'qul' => 'QUH QUZ ', /* North Bolivian Quechua */
		'qum' => 'MYN ', /* Sipacapense */
		'qup' => 'QVI QUZ ', /* Southern Pastaza Quechua */
		'qur' => 'QWH QUZ ', /* Yanahuanca Pasco Quechua */
		'qus' => 'QUH QUZ ', /* Santiago del Estero Quichua */
		'quv' => 'MYN ', /* Sacapulteco */
		'quw' => 'QVI QUZ ', /* Tena Lowland Quichua */
		'qux' => 'QWH QUZ ', /* Yauyos Quechua */
		'quy' => 'QUZ ', /* Ayacucho Quechua */
		'qva' => 'QWH QUZ ', /* Ambo-Pasco Quechua */
		'qvc' => 'QUZ ', /* Cajamarca Quechua */
		'qve' => 'QUZ ', /* Eastern Apurímac Quechua */
		'qvh' => 'QWH QUZ ', /* Huamalíes-Dos de Mayo Huánuco Quechua */
		'qvi' => 'QVI QUZ ', /* Imbabura Highland Quichua */
		'qvj' => 'QVI QUZ ', /* Loja Highland Quichua */
		'qvl' => 'QWH QUZ ', /* Cajatambo North Lima Quechua */
		'qvm' => 'QWH QUZ ', /* Margos-Yarowilca-Lauricocha Quechua */
		'qvn' => 'QWH QUZ ', /* North Junín Quechua */
		'qvo' => 'QVI QUZ ', /* Napo Lowland Quechua */
		'qvp' => 'QWH QUZ ', /* Pacaraos Quechua */
		'qvs' => 'QUZ ', /* San Martín Quechua */
		'qvw' => 'QWH QUZ ', /* Huaylla Wanca Quechua */
		'qvz' => 'QVI QUZ ', /* Northern Pastaza Quichua */
		'qwa' => 'QWH QUZ ', /* Corongo Ancash Quechua */
		'qwc' => 'QUZ ', /* Classical Quechua */
		'qwh' => 'QWH QUZ ', /* Huaylas Ancash Quechua */
		'qws' => 'QWH QUZ ', /* Sihuas Ancash Quechua */
		'qwt' => 'ATH ', /* Kwalhioqua-Tlatskanai */
		'qxa' => 'QWH QUZ ', /* Chiquián Ancash Quechua */
		'qxc' => 'QWH QUZ ', /* Chincha Quechua */
		'qxh' => 'QWH QUZ ', /* Panao Huánuco Quechua */
		'qxl' => 'QVI QUZ ', /* Salasaca Highland Quichua */
		'qxn' => 'QWH QUZ ', /* Northern Conchucos Ancash Quechua */
		'qxo' => 'QWH QUZ ', /* Southern Conchucos Ancash Quechua */
		'qxp' => 'QUZ ', /* Puno Quechua */
		'qxr' => 'QVI QUZ ', /* Cañar Highland Quichua */
		'qxt' => 'QWH QUZ ', /* Santa Ana de Tusi Pasco Quechua */
		'qxu' => 'QUZ ', /* Arequipa-La Unión Quechua */
		'qxw' => 'QWH QUZ ', /* Jauja Wanca Quechua */
		'rag' => 'LUH ', /* Logooli */
		'ral' => 'QIN ', /* Ralte */
		'rbb' => 'PLG ', /* Rumai Palaung */
		'rbl' => 'BIK ', /* Miraya Bikol */
		'rcf' => 'CPP ', /* Réunion Creole French */
		'rif' => 'RIF BBR ', /* Tarifit */
		'rki' => 'ARK ', /* Rakhine */
		'rm' => 'RMS ', /* Romansh */
		'rmc' => 'ROY ', /* Carpathian Romani */
		'rmf' => 'ROY ', /* Kalo Finnish Romani */
		'rml' => 'ROY ', /* Baltic Romani */
		'rmn' => 'ROY ', /* Balkan Romani */
		'rmo' => 'ROY ', /* Sinte Romani */
		'rms' => '', /* Romanian Sign Language != Romansh */
		'rmw' => 'ROY ', /* Welsh Romani */
		'rmy' => 'RMY ROY ', /* Vlax Romani */
		'rmz' => 'ARK ', /* Marma */
		'rn' => 'RUN ', /* Rundi */
		'ro' => 'ROM ', /* Romanian */
		'rom' => 'ROY ', /* Romany [macrolanguage] */
		'rop' => 'CPP ', /* Kriol */
		'rtc' => 'QIN ', /* Rungtu Chin */
		'ru' => 'RUS ', /* Russian */
		'rue' => 'RSY ', /* Rusyn */
		'rw' => 'RUA ', /* Kinyarwanda */
		'rwr' => 'MAW ', /* Marwari (India) */
		'sa' => 'SAN ', /* Sanskrit [macrolanguage] */
		'sad' => '', /* Sandawe != Sadri */
		'sah' => 'YAK ', /* Yakut */
		'sam' => 'PAA ', /* Samaritan Aramaic */
		'say' => '', /* Saya != Sayisi */
		'sc' => 'SRD ', /* Sardinian [macrolanguage] */
		'scf' => 'CPP ', /* San Miguel Creole French */
		'sch' => 'QIN ', /* Sakachep */
		'sci' => 'CPP ', /* Sri Lankan Creole Malay */
		'sck' => 'SAD ', /* Sadri */
		'scs' => 'SCS SLA ATH ', /* North Slavey */
		'sd' => 'SND ', /* Sindhi */
		'sdc' => 'SRD ', /* Sassarese Sardinian */
		'sdh' => 'KUR ', /* Southern Kurdish */
		'sdn' => 'SRD ', /* Gallurese Sardinian */
		'sds' => 'BBR ', /* Sened */
		'se' => 'NSM ', /* Northern Sami */
		'seh' => 'SNA ', /* Sena */
		'sek' => 'ATH ', /* Sekani */
		'sez' => 'QIN ', /* Senthang Chin */
		'sfm' => 'SFM HMN ', /* Small Flowery Miao */
		'sg' => 'SGO ', /* Sango */
		'sgc' => 'KAL ', /* Kipsigis */
		'sgo' => '', /* Songa (retired code) != Sango */
		'sgw' => 'CHG ', /* Sebat Bet Gurage */
		'sh' => 'BOS HRV SRB ', /* Serbo-Croatian [macrolanguage] */
		'shi' => 'SHI BBR ', /* Tachelhit */
		'shl' => 'QIN ', /* Shendu */
		'shu' => 'ARA ', /* Chadian Arabic */
		'shy' => 'BBR ', /* Tachawit */
		'si' => 'SNH ', /* Sinhala (Sinhalese) */
		'sib' => '', /* Sebop != Sibe */
		'sig' => '', /* Paasaal != Silte Gurage */
		'siz' => 'BBR ', /* Siwi */
		'sjc' => 'ZHS ', /* Shaojiang Chinese */
		'sjd' => 'KSM ', /* Kildin Sami */
		'sjo' => 'SIB ', /* Xibe */
		'sjs' => 'BBR ', /* Senhaja De Srair */
		'sk' => 'SKY ', /* Slovak */
		'skg' => 'MLG ', /* Sakalava Malagasy */
		'skr' => 'SRK ', /* Saraiki */
		'sks' => '', /* Maia != Skolt Sami */
		'skw' => 'CPP ', /* Skepi Creole Dutch */
		'sky' => '', /* Sikaiana != Slovak */
		'sl' => 'SLV ', /* Slovenian */
		'sla' => '', /* Slavic [collection] != Slavey */
		'sm' => 'SMO ', /* Samoan */
		'sma' => 'SSM ', /* Southern Sami */
		'smd' => 'MBN ', /* Sama (retired code) */
		'smj' => 'LSM ', /* Lule Sami */
		'sml' => '', /* Central Sama != Somali */
		'smn' => 'ISM ', /* Inari Sami */
		'sms' => 'SKS ', /* Skolt Sami */
		'smt' => 'QIN ', /* Simte */
		'sn' => 'SNA0', /* Shona */
		'snb' => 'IBA ', /* Sebuyau (retired code) */
		'snh' => '', /* Shinabo (retired code) != Sinhala (Sinhalese) */
		'so' => 'SML ', /* Somali */
		'sog' => '', /* Sogdian != Sodo Gurage */
		'spv' => 'ORI ', /* Sambalpuri */
		'spy' => 'KAL ', /* Sabaot */
		'sq' => 'SQI ', /* Albanian [macrolanguage] */
		'sr' => 'SRB ', /* Serbian */
		'srb' => '', /* Sora != Serbian */
		'src' => 'SRD ', /* Logudorese Sardinian */
		'srk' => '', /* Serudung Murut != Saraiki */
		'srm' => 'CPP ', /* Saramaccan */
		'srn' => 'CPP ', /* Sranan Tongo */
		'sro' => 'SRD ', /* Campidanese Sardinian */
		'srs' => 'ATH ', /* Tsuut'ina */
		'ss' => 'SWZ ', /* Swati */
		'ssh' => 'ARA ', /* Shihhi Arabic */
		'ssl' => '', /* Western Sisaala != South Slavey */
		'ssm' => '', /* Semnam != Southern Sami */
		'st' => 'SOT ', /* Southern Sotho */
		'sta' => 'CPP ', /* Settla */
		'stv' => 'SIG ', /* Silt'e */
		'su' => 'SUN ', /* Sundanese */
		'suq' => 'SUR ', /* Suri */
		'sur' => '', /* Mwaghavul != Suri */
		'sv' => 'SVE ', /* Swedish */
		'svc' => 'CPP ', /* Vincentian Creole English */
		'sve' => '', /* Serili != Swedish */
		'sw' => 'SWK ', /* Swahili [macrolanguage] */
		'swb' => 'CMR ', /* Maore Comorian */
		'swc' => 'SWK ', /* Congo Swahili */
		'swh' => 'SWK ', /* Swahili */
		'swk' => '', /* Malawi Sena != Swahili */
		'swn' => 'BBR ', /* Sawknah */
		'swv' => 'MAW ', /* Shekhawati */
		'syc' => 'SYR ', /* Classical Syriac */
		'ta' => 'TAM ', /* Tamil */
		'taa' => 'ATH ', /* Lower Tanana */
		'taj' => '', /* Eastern Tamang != Tajiki */
		'taq' => 'TAQ TMH BBR ', /* Tamasheq */
		'tas' => 'CPP ', /* Tay Boi */
		'tau' => 'ATH ', /* Upper Tanana */
		'tcb' => 'ATH ', /* Tanacross */
		'tce' => 'ATH ', /* Southern Tutchone */
		'tch' => 'CPP ', /* Turks And Caicos Creole English */
		'tcp' => 'QIN ', /* Tawr Chin */
		'tcs' => 'CPP ', /* Torres Strait Creole */
		'tcy' => 'TUL ', /* Tulu */
		'tcz' => 'QIN ', /* Thado Chin */
		'tdx' => 'MLG ', /* Tandroy-Mahafaly Malagasy */
		'te' => 'TEL ', /* Telugu */
		'tec' => 'KAL ', /* Terik */
		'tem' => 'TMN ', /* Timne */
		'tez' => 'BBR ', /* Tetserret */
		'tfn' => 'ATH ', /* Tanaina */
		'tg' => 'TAJ ', /* Tajik */
		'tgh' => 'CPP ', /* Tobagonian Creole English */
		'tgj' => 'NIS ', /* Tagin */
		'tgn' => '', /* Tandaganon != Tongan */
		'tgr' => '', /* Tareng != Tigre */
		'tgx' => 'ATH ', /* Tagish */
		'tgy' => '', /* Togoyo != Tigrinya */
		'th' => 'THA ', /* Thai */
		'tht' => 'ATH ', /* Tahltan */
		'thv' => 'THV TMH BBR ', /* Tahaggart Tamahaq */
		'thz' => 'THZ TMH BBR ', /* Tayart Tamajeq */
		'ti' => 'TGY ', /* Tigrinya */
		'tia' => 'BBR ', /* Tidikelt Tamazight */
		'tig' => 'TGR ', /* Tigre */
		'tjo' => 'BBR ', /* Temacine Tamazight */
		'tk' => 'TKM ', /* Turkmen */
		'tkg' => 'MLG ', /* Tesaka Malagasy */
		'tkm' => '', /* Takelma != Turkmen */
		'tl' => 'TGL ', /* Tagalog */
		'tmg' => 'CPP ', /* Ternateño */
		'tmh' => 'TMH BBR ', /* Tamashek [macrolanguage] */
		'tmn' => '', /* Taman (Indonesia) != Temne */
		'tmw' => 'MLY ', /* Temuan */
		'tn' => 'TNA ', /* Tswana */
		'tna' => '', /* Tacana != Tswana */
		'tne' => '', /* Tinoc Kallahan (retired code) != Tundra Enets */
		'tnf' => 'DRI FAR ', /* Tangshewi (retired code) */
		'tng' => '', /* Tobanga != Tonga */
		'to' => 'TGN ', /* Tonga (Tonga Islands) */
		'tod' => 'TOD0', /* Toma */
		'toi' => 'TNG ', /* Tonga (Zambia) */
		'toj' => 'MYN ', /* Tojolabal */
		'tol' => 'ATH ', /* Tolowa */
		'tor' => 'BAD0', /* Togbo-Vara Banda */
		'tpi' => 'TPI CPP ', /* Tok Pisin */
		'tr' => 'TRK ', /* Turkish */
		'trf' => 'CPP ', /* Trinidadian Creole English */
		'trk' => '', /* Turkic [collection] != Turkish */
		'tru' => 'TUA SYR ', /* Turoyo */
		'ts' => 'TSG ', /* Tsonga */
		'tsg' => '', /* Tausug != Tsonga */
		'tt' => 'TAT ', /* Tatar */
		'ttc' => 'MYN ', /* Tektiteko */
		'ttm' => 'ATH ', /* Northern Tutchone */
		'ttq' => 'TTQ TMH BBR ', /* Tawallammat Tamajaq */
		'tua' => '', /* Wiarumus != Turoyo Aramaic */
		'tul' => '', /* Tula != Tulu */
		'tuu' => 'ATH ', /* Tututni */
		'tuv' => '', /* Turkana != Tuvin */
		'tuy' => 'KAL ', /* Tugen */
		'tvy' => 'CPP ', /* Timor Pidgin */
		'tw' => 'TWI AKA ', /* Twi */
		'txc' => 'ATH ', /* Tsetsaut */
		'txy' => 'MLG ', /* Tanosy Malagasy */
		'ty' => 'THT ', /* Tahitian */
		'tyv' => 'TUV ', /* Tuvinian */
		'tzh' => 'MYN ', /* Tzeltal */
		'tzj' => 'MYN ', /* Tz'utujil */
		'tzm' => 'TZM BBR ', /* Central Atlas Tamazight */
		'tzo' => 'TZO MYN ', /* Tzotzil */
		'ubl' => 'BIK ', /* Buhi'non Bikol */
		'ug' => 'UYG ', /* Uyghur */
		'uk' => 'UKR ', /* Ukrainian */
		'uki' => 'KUI ', /* Kui (India) */
		'uln' => 'CPP ', /* Unserdeutsch */
		'unr' => 'MUN ', /* Mundari */
		'ur' => 'URD ', /* Urdu */
		'urk' => 'MLY ', /* Urak Lawoi' */
		'usp' => 'MYN ', /* Uspanteco */
		'uz' => 'UZB ', /* Uzbek [macrolanguage] */
		'uzn' => 'UZB ', /* Northern Uzbek */
		'uzs' => 'UZB ', /* Southern Uzbek */
		'vap' => 'QIN ', /* Vaiphei */
		've' => 'VEN ', /* Venda */
		'vi' => 'VIT ', /* Vietnamese */
		'vic' => 'CPP ', /* Virgin Islands Creole English */
		'vit' => '', /* Viti != Vietnamese */
		'vkk' => 'MLY ', /* Kaur */
		'vkp' => 'CPP ', /* Korlai Creole Portuguese */
		'vkt' => 'MLY ', /* Tenggarong Kutai Malay */
		'vls' => 'FLE ', /* Vlaams */
		'vmw' => 'MAK ', /* Makhuwa */
		'vo' => 'VOL ', /* Volapük */
		'vro' => 'VRO ETI ', /* Võro */
		'vsn' => 'SAN ', /* Vedic Sanskrit */
		'wa' => 'WLN ', /* Walloon */
		'wag' => '', /* Wa'ema != Wagdi */
		'wbm' => 'WA  ', /* Wa */
		'wbr' => 'WAG RAJ ', /* Wagdi */
		'wea' => 'KRN ', /* Wewaw */
		'wes' => 'CPP ', /* Cameroon Pidgin */
		'weu' => 'QIN ', /* Rawngtu Chin */
		'wlc' => 'CMR ', /* Mwali Comorian */
		'wle' => 'SIG ', /* Wolane */
		'wlk' => 'ATH ', /* Wailaki */
		'wni' => 'CMR ', /* Ndzwani Comorian */
		'wo' => 'WLF ', /* Wolof */
		'wry' => 'MAW ', /* Merwari */
		'wsg' => 'GON ', /* Adilabad Gondi */
		'wuu' => 'ZHS ', /* Wu Chinese */
		'wya' => 'WDT WYN ', /* Wyandot (retired code) */
		'xal' => 'KLM TOD ', /* Kalmyk */
		'xan' => 'SEK ', /* Xamtanga */
		'xbd' => '', /* Bindal != Lü */
		'xh' => 'XHS ', /* Xhosa */
		'xmg' => 'BML ', /* Mengaka */
		'xmm' => 'MLY CPP ', /* Manado Malay */
		'xmv' => 'MLG ', /* Antankarana Malagasy */
		'xmw' => 'MLG ', /* Tsimihety Malagasy */
		'xnj' => 'SXT ', /* Ngoni (Tanzania) */
		'xnq' => 'SXT ', /* Ngoni (Mozambique) */
		'xnr' => 'DGR ', /* Kangri */
		'xpe' => 'XPE KPL ', /* Liberia Kpelle */
		'xsl' => 'SSL SLA ATH ', /* South Slavey */
		'xst' => 'SIG ', /* Silt'e (retired code) */
		'xup' => 'ATH ', /* Upper Umpqua */
		'xwo' => 'TOD ', /* Written Oirat */
		'yaj' => 'BAD0', /* Banda-Yangere */
		'yak' => '', /* Yakama != Sakha */
		'yba' => '', /* Yala != Yoruba */
		'ybb' => 'BML ', /* Yemba */
		'ybd' => 'ARK ', /* Yangbye (retired code) */
		'ycr' => 'CPP ', /* Yilan Creole */
		'ydd' => 'JII ', /* Eastern Yiddish */
		'yi' => 'JII ', /* Yiddish [macrolanguage] */
		'yih' => 'JII ', /* Western Yiddish */
		'yim' => '', /* Yimchungru Naga != Yi Modern */
		'yo' => 'YBA ', /* Yoruba */
		'yos' => 'QIN ', /* Yos (retired code) */
		'yua' => 'MYN ', /* Yucateco */
		'yue' => 'ZHH ', /* Yue Chinese */
		'za' => 'ZHA ', /* Zhuang [macrolanguage] */
		'zch' => 'ZHA ', /* Central Hongshuihe Zhuang */
		'zdj' => 'CMR ', /* Ngazidja Comorian */
		'zeh' => 'ZHA ', /* Eastern Hongshuihe Zhuang */
		'zen' => 'BBR ', /* Zenaga */
		'zgb' => 'ZHA ', /* Guibei Zhuang */
		'zgh' => 'ZGH BBR ', /* Standard Moroccan Tamazight */
		'zgm' => 'ZHA ', /* Minz Zhuang */
		'zgn' => 'ZHA ', /* Guibian Zhuang */
		'zh' => 'ZHS ', /* Chinese, Simplified [macrolanguage] */
		'zhd' => 'ZHA ', /* Dai Zhuang */
		'zhn' => 'ZHA ', /* Nong Zhuang */
		'zkb' => 'KHA ', /* Koibal (retired code) */
		'zlj' => 'ZHA ', /* Liujiang Zhuang */
		'zlm' => 'MLY ', /* Malay */
		'zln' => 'ZHA ', /* Lianshan Zhuang */
		'zlq' => 'ZHA ', /* Liuqian Zhuang */
		'zmi' => 'MLY ', /* Negeri Sembilan Malay */
		'zmz' => 'BAD0', /* Mbandja */
		'znd' => '', /* Zande [collection] != Zande */
		'zne' => 'ZND ', /* Zande */
		'zom' => 'QIN ', /* Zou */
		'zqe' => 'ZHA ', /* Qiubei Zhuang */
		'zsm' => 'MLY ', /* Standard Malay */
		'zu' => 'ZUL ', /* Zulu */
		'zum' => 'LRC ', /* Kumzari */
		'zyb' => 'ZHA ', /* Yongbei Zhuang */
		'zyg' => 'ZHA ', /* Yang Zhuang */
		'zyj' => 'ZHA ', /* Youjiang Zhuang */
		'zyn' => 'ZHA ', /* Yongnan Zhuang */
		'zyp' => 'QIN ', /* Zyphe Chin */
		'zzj' => 'ZHA ', /* Zuojiang Zhuang */
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

	// UNIDATA_VERSION 18.0.0
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
		[12, 0, 0, 0, 5, 0, 61],
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
		[14, 0, 0, 0, 5, 0, 176],
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
		[7, 0, 0, 0, 2, 0, 175],
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
		[7, 0, 0, 0, 2, 0, 177],
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
		8733 => 121616,
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
		11874 => 11875,
		11875 => 11874,
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
		121603 => 121604,
		121604 => 121603,
		121605 => 121606,
		121606 => 121605,
		121608 => 121609,
		121609 => 121608,
		121616 => 8733,
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
		106, 106, 107, 108, 109, 110, 111, 111, 111, 111, 111, 111, 111, 111, 112, 113, 113, 113, 113,
		114, 113, 113, 113, 113, 113, 113, 113, 113, 113, 113, 113, 113, 113, 113, 115, 116, 116, 117,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 118, 111, 111, 111, 111, 111, 111, 119, 119, 120, 121, 111,
		122, 123, 124, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125,
		125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 126, 127, 128, 129, 129, 129, 130, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 131, 132, 133, 134, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 135, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 148, 149, 150, 111, 111, 111,
		151, 152, 153, 154, 111, 155, 156, 157, 158, 159, 160, 161, 161, 162, 163, 164, 161, 165, 166,
		167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 111, 111, 111, 177, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 178, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 179, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 180, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 181, 52, 52, 182, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 52, 52, 184, 183, 183, 183, 183, 185, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 186, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52,
		52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 52, 187, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183,
		183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 183, 188, 188,
		188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188,
		188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188, 188,
		188, 188, 188, 188, 189, 183, 183, 185, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 177, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 177, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 177, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 177, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 177, 190, 191, 192, 192, 192, 192, 192, 192, 192, 192, 192, 192, 192, 192, 192, 192,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111,
		111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 111, 177, 67, 67, 67, 67, 67, 67, 67, 67, 67,
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
		67, 67, 67, 67, 67, 67, 193, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67,
		67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 67, 193,
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
		118, 119, 120, 120, 120, 121, 122, 123, 124, 125, 126, 127, 128, 128, 128, 128, 129, 130, 131,
		132, 133, 134, 135, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 136, 137, 138, 139,
		140, 141, 142, 143, 144, 145, 145, 145, 146, 147, 148, 149, 128, 128, 128, 128, 128, 128, 150,
		150, 150, 150, 151, 152, 153, 154, 155, 156, 157, 157, 157, 158, 159, 160, 161, 161, 162, 163,
		164, 165, 166, 167, 168, 168, 168, 169, 145, 170, 128, 128, 128, 171, 172, 173, 128, 128, 128,
		128, 128, 174, 175, 126, 176, 177, 178, 179, 180, 181, 181, 181, 181, 181, 181, 182, 183, 184,
		185, 181, 186, 187, 188, 181, 189, 190, 191, 192, 192, 193, 194, 195, 196, 197, 198, 199, 200,
		201, 202, 203, 204, 205, 206, 207, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218,
		219, 220, 221, 222, 222, 223, 224, 225, 226, 227, 228, 218, 229, 230, 231, 232, 233, 234, 235,
		236, 236, 237, 238, 239, 240, 241, 242, 243, 244, 245, 246, 218, 247, 248, 249, 250, 251, 248,
		252, 253, 254, 255, 256, 218, 257, 258, 259, 260, 261, 262, 263, 264, 264, 263, 264, 265, 266,
		267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277, 277, 276, 278, 279, 280, 281, 282, 283,
		284, 285, 286, 218, 287, 288, 289, 290, 290, 290, 290, 291, 292, 293, 294, 295, 296, 297, 298,
		299, 300, 301, 302, 303, 301, 301, 304, 305, 302, 306, 307, 308, 309, 310, 311, 218, 312, 313,
		313, 313, 313, 313, 314, 315, 316, 317, 318, 319, 218, 218, 218, 218, 320, 321, 322, 322, 323,
		322, 324, 325, 326, 327, 328, 329, 218, 218, 218, 218, 330, 331, 332, 333, 334, 335, 336, 337,
		338, 339, 338, 338, 338, 340, 341, 342, 343, 344, 345, 346, 345, 345, 345, 347, 348, 349, 350,
		351, 218, 218, 218, 218, 352, 352, 352, 352, 352, 353, 354, 355, 356, 357, 358, 359, 360, 361,
		362, 352, 363, 364, 356, 365, 366, 366, 366, 366, 367, 368, 369, 369, 369, 369, 369, 370, 371,
		371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 372, 372, 372, 372, 372, 372, 372, 372,
		372, 372, 372, 372, 372, 372, 372, 372, 372, 372, 372, 372, 373, 373, 373, 373, 373, 373, 373,
		373, 373, 374, 375, 374, 373, 373, 373, 373, 373, 374, 373, 373, 373, 373, 374, 375, 374, 373,
		375, 373, 373, 373, 373, 373, 373, 373, 374, 373, 373, 373, 373, 373, 373, 373, 373, 376, 377,
		378, 379, 380, 373, 373, 381, 382, 383, 383, 383, 383, 383, 383, 383, 383, 383, 383, 384, 385,
		386, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387,
		387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387,
		387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387,
		387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387,
		387, 388, 387, 387, 389, 390, 390, 391, 392, 392, 392, 392, 392, 392, 392, 392, 392, 393, 394,
		395, 396, 396, 397, 398, 399, 399, 400, 218, 401, 401, 402, 218, 403, 404, 405, 218, 406, 406,
		406, 406, 406, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 419, 420, 421,
		421, 421, 421, 422, 421, 421, 421, 421, 421, 421, 423, 424, 421, 421, 421, 421, 425, 387, 387,
		387, 387, 387, 387, 387, 387, 426, 218, 427, 427, 427, 428, 429, 430, 431, 432, 433, 434, 435,
		435, 435, 436, 437, 218, 438, 438, 438, 438, 438, 439, 438, 438, 438, 440, 441, 442, 443, 443,
		443, 443, 444, 444, 445, 446, 447, 447, 447, 447, 447, 447, 448, 449, 450, 451, 452, 453, 454,
		455, 454, 455, 456, 457, 458, 459, 460, 461, 462, 463, 464, 465, 466, 218, 467, 468, 468, 468,
		468, 468, 469, 470, 471, 472, 473, 474, 475, 476, 477, 478, 479, 480, 480, 480, 481, 482, 483,
		484, 485, 485, 485, 485, 486, 487, 488, 489, 490, 490, 490, 490, 491, 492, 493, 494, 495, 496,
		497, 498, 499, 499, 499, 500, 100, 501, 366, 366, 366, 366, 366, 502, 503, 218, 504, 505, 506,
		507, 508, 509, 54, 54, 54, 54, 510, 511, 56, 56, 56, 56, 56, 512, 513, 514, 54, 515, 54, 54, 54,
		516, 56, 56, 56, 517, 461, 518, 519, 462, 462, 462, 520, 521, 27, 27, 27, 27, 27, 27, 27, 27, 27,
		27, 27, 27, 27, 27, 27, 27, 27, 27, 522, 523, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 27, 524,
		525, 526, 527, 524, 525, 524, 525, 526, 527, 524, 528, 524, 525, 524, 526, 524, 529, 524, 529,
		524, 529, 530, 531, 532, 533, 534, 535, 524, 536, 537, 538, 539, 540, 541, 542, 543, 544, 545,
		546, 547, 548, 549, 550, 551, 552, 553, 554, 555, 556, 56, 56, 557, 558, 557, 557, 559, 560, 561,
		562, 563, 564, 466, 218, 565, 566, 567, 568, 569, 570, 571, 572, 573, 574, 575, 576, 577, 578,
		577, 579, 580, 581, 582, 583, 584, 585, 586, 587, 586, 588, 589, 586, 590, 586, 591, 592, 593,
		594, 595, 596, 597, 598, 599, 600, 601, 602, 603, 604, 605, 606, 601, 601, 607, 608, 609, 610,
		611, 601, 601, 612, 592, 613, 614, 601, 601, 615, 601, 601, 586, 616, 617, 618, 619, 620, 621,
		622, 622, 622, 622, 622, 622, 622, 622, 623, 586, 586, 624, 625, 592, 592, 626, 586, 586, 586,
		586, 591, 627, 628, 629, 586, 586, 586, 586, 586, 586, 630, 218, 218, 586, 631, 218, 218, 632,
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
		92, 92, 92, 724, 725, 726, 727, 369, 369, 369, 369, 728, 729, 730, 730, 730, 730, 730, 730, 730,
		731, 732, 733, 373, 373, 375, 218, 375, 375, 375, 375, 375, 375, 375, 375, 734, 734, 734, 734,
		735, 736, 737, 738, 739, 740, 548, 741, 742, 548, 743, 744, 745, 218, 218, 218, 746, 746, 746,
		747, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 748, 218, 746, 746, 746, 746, 746, 746,
		746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746, 746,
		746, 749, 218, 218, 218, 650, 650, 750, 751, 752, 753, 754, 755, 756, 757, 758, 759, 759, 759,
		759, 759, 759, 759, 759, 759, 760, 761, 762, 763, 763, 763, 763, 763, 763, 763, 763, 763, 763,
		764, 765, 766, 766, 766, 766, 766, 767, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 768,
		769, 770, 766, 766, 766, 766, 650, 650, 650, 650, 771, 772, 763, 763, 773, 773, 773, 774, 775,
		776, 770, 770, 770, 777, 778, 779, 773, 773, 773, 780, 775, 776, 770, 770, 770, 770, 781, 779,
		770, 782, 783, 783, 783, 783, 783, 784, 783, 783, 783, 783, 783, 783, 783, 783, 783, 783, 783,
		770, 770, 770, 785, 786, 770, 770, 770, 770, 770, 770, 770, 770, 770, 770, 770, 787, 770, 770,
		770, 785, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 650, 650, 650, 650, 650, 650, 650, 650, 789, 789, 790, 789, 789, 789, 789, 789, 789, 789,
		789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789,
		789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789,
		789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789,
		789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 789, 791, 792, 792, 792, 792,
		792, 792, 793, 218, 794, 794, 794, 794, 794, 795, 796, 796, 796, 796, 796, 796, 796, 796, 796,
		796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796, 796,
		796, 796, 796, 796, 796, 797, 796, 796, 798, 799, 218, 218, 101, 101, 101, 101, 101, 800, 801,
		802, 101, 101, 101, 803, 804, 804, 804, 804, 804, 804, 804, 804, 805, 806, 807, 218, 64, 64, 808,
		809, 810, 27, 811, 27, 27, 27, 27, 27, 27, 27, 812, 813, 27, 814, 815, 27, 27, 816, 817, 27, 818,
		819, 27, 820, 821, 218, 822, 823, 824, 825, 826, 826, 827, 828, 829, 830, 831, 831, 831, 831, 831,
		831, 832, 218, 833, 834, 834, 834, 834, 834, 835, 836, 837, 838, 839, 840, 841, 841, 842, 843,
		844, 845, 846, 846, 847, 848, 849, 849, 850, 851, 852, 853, 371, 371, 371, 854, 855, 856, 856,
		856, 856, 856, 857, 858, 859, 860, 861, 862, 863, 352, 356, 864, 865, 865, 865, 865, 865, 866,
		867, 218, 868, 869, 870, 871, 352, 352, 872, 873, 874, 874, 874, 874, 874, 874, 875, 876, 877,
		218, 218, 878, 879, 880, 881, 218, 882, 882, 882, 218, 375, 375, 54, 54, 54, 54, 54, 883, 884,
		885, 886, 886, 886, 886, 886, 886, 886, 886, 886, 886, 879, 879, 879, 879, 887, 888, 889, 890,
		371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371,
		371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371,
		371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 371, 891, 218, 372, 372, 892,
		893, 372, 372, 372, 372, 372, 894, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895,
		895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895, 895,
		895, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896,
		896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 897, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 898, 899, 899, 899, 899, 900, 218, 901, 902, 120, 903, 904, 905, 906, 120, 128,
		128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 907, 908, 909, 910, 911, 128, 128, 128,
		128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128,
		128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128,
		128, 128, 128, 912, 910, 910, 128, 128, 128, 128, 128, 128, 128, 128, 913, 128, 128, 128, 128,
		128, 128, 910, 914, 914, 914, 914, 128, 915, 916, 916, 917, 918, 919, 920, 921, 922, 923, 924,
		925, 926, 927, 928, 929, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128, 128,
		128, 128, 930, 931, 932, 933, 934, 935, 936, 936, 937, 938, 939, 939, 940, 941, 942, 943, 942,
		942, 942, 942, 944, 945, 945, 945, 946, 947, 947, 947, 948, 949, 950, 914, 951, 952, 953, 952,
		952, 954, 952, 952, 955, 952, 956, 952, 956, 218, 218, 218, 218, 952, 952, 952, 952, 952, 952,
		952, 952, 952, 952, 952, 952, 952, 952, 952, 957, 958, 959, 959, 959, 959, 959, 960, 622, 961,
		961, 961, 961, 961, 961, 962, 963, 964, 965, 586, 966, 967, 218, 218, 218, 218, 218, 622, 622,
		622, 622, 622, 968, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 969, 969, 969, 970, 971, 971, 971, 971, 971, 971, 972, 218, 973, 974, 974, 975, 976, 976,
		976, 976, 977, 978, 979, 979, 980, 981, 982, 982, 982, 982, 983, 984, 985, 985, 985, 986, 987,
		987, 987, 987, 988, 987, 989, 218, 218, 218, 218, 218, 990, 990, 990, 990, 990, 991, 991, 991,
		991, 991, 992, 992, 992, 992, 992, 992, 993, 993, 993, 994, 995, 996, 997, 997, 997, 997, 998,
		999, 999, 999, 999, 1000, 1001, 1001, 1001, 1001, 1001, 218, 1002, 1002, 1002, 1002, 1002, 1002,
		1003, 1004, 1005, 1006, 1005, 1006, 1007, 1008, 1009, 1008, 1009, 1010, 1011, 1011, 1011, 1011,
		1011, 1011, 1012, 218, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013,
		1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013,
		1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1013, 1014, 218, 1013, 1013, 1015, 218,
		1013, 218, 218, 218, 1016, 56, 56, 56, 56, 56, 1017, 56, 218, 218, 218, 218, 218, 218, 218, 218,
		1018, 1019, 1020, 1020, 1020, 1020, 1021, 1022, 1023, 1023, 1024, 1025, 1026, 1026, 1027, 1028,
		1029, 1029, 1029, 1030, 1031, 1032, 123, 123, 123, 123, 123, 123, 1033, 1033, 1034, 1035, 1036,
		1036, 1037, 1038, 1039, 1039, 1039, 1040, 1041, 1041, 1041, 1042, 123, 123, 123, 123, 1043, 1043,
		1043, 1043, 1044, 1044, 1044, 1045, 1046, 1046, 1047, 1046, 1046, 1046, 1046, 1046, 1048, 1049,
		1050, 1051, 1052, 1052, 1053, 1054, 1055, 1056, 1057, 1058, 1059, 1059, 1059, 1060, 1061, 1061,
		1061, 1062, 123, 123, 123, 123, 1063, 1064, 1063, 1063, 1065, 1066, 1067, 123, 1068, 1068, 1068,
		1068, 1068, 1068, 1069, 1070, 1071, 1071, 1072, 1073, 1074, 1074, 1075, 1076, 1077, 1077, 1078,
		1079, 123, 1080, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 1081, 1081, 1081, 1081, 1081,
		1081, 1081, 1081, 1081, 1082, 123, 123, 123, 123, 123, 123, 1083, 1083, 1083, 1083, 1083, 1083,
		1084, 123, 1085, 1085, 1085, 1085, 1085, 1085, 1086, 1087, 1088, 1088, 1088, 1088, 1089, 154,
		1090, 1091, 1092, 1093, 1094, 1094, 1095, 1096, 1097, 1097, 1098, 1099, 123, 123, 123, 123, 123,
		123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123,
		123, 123, 1100, 1100, 1100, 1101, 1102, 1102, 1102, 1102, 1102, 1103, 1104, 123, 1105, 1106, 1107,
		1108, 128, 1109, 1110, 1111, 1112, 1112, 1112, 1113, 1114, 123, 1115, 1115, 1116, 1117, 1118,
		1119, 154, 154, 1120, 1120, 1121, 1122, 123, 123, 123, 123, 1123, 1123, 1124, 1125, 123, 123,
		1126, 1126, 1127, 123, 1128, 1129, 1129, 1129, 1129, 1129, 1129, 1130, 1131, 1132, 1133, 1134,
		1135, 1136, 1137, 1138, 1139, 1140, 1140, 1140, 1140, 1140, 1141, 1142, 1143, 1144, 1145, 1145,
		1145, 1146, 1147, 1148, 1149, 1150, 1150, 1150, 1151, 1152, 1153, 1154, 1155, 218, 1156, 1156,
		1156, 1156, 1157, 218, 1158, 1159, 1159, 1159, 1159, 1159, 1160, 1161, 1162, 1163, 1164, 1165,
		1166, 1167, 1168, 218, 1169, 1169, 1170, 1169, 1169, 1171, 1172, 1173, 1174, 218, 218, 218, 218,
		218, 218, 218, 1175, 1176, 1177, 1178, 1177, 1179, 1180, 1180, 1180, 1180, 1180, 1181, 1182, 1183,
		1184, 1185, 1186, 1187, 1188, 1189, 1189, 1190, 1191, 1192, 1193, 1194, 1195, 1196, 1197, 1198,
		1198, 218, 1199, 1200, 1199, 1199, 1199, 1199, 1201, 1202, 1203, 1204, 1205, 1206, 1207, 218, 218,
		218, 1208, 1208, 1208, 1208, 1208, 1208, 1209, 1210, 1211, 1212, 1213, 1214, 1215, 218, 218, 218,
		1216, 1216, 1216, 1216, 1216, 1216, 1217, 1218, 1219, 218, 1220, 1221, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1222, 1222, 1222, 1222,
		1222, 1223, 1224, 1225, 1226, 1227, 1227, 1228, 218, 218, 218, 218, 1229, 1229, 1229, 1229, 1229,
		1229, 1230, 1231, 1232, 218, 1233, 1234, 1235, 1236, 218, 218, 1237, 1237, 1237, 1237, 1237, 1238,
		1239, 1240, 1241, 1242, 356, 356, 1243, 218, 218, 218, 1244, 1244, 1244, 1245, 1246, 1247, 1248,
		1249, 1250, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 1251, 1251, 1251, 1251, 1251, 1252, 1253, 1254, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 1255, 1255, 1255, 1255, 1256, 1256, 1256, 1256, 1257,
		1258, 1259, 1260, 1261, 1262, 1263, 1264, 1264, 1264, 1265, 1266, 1267, 218, 1268, 1269, 218, 218,
		218, 218, 218, 218, 218, 218, 1270, 1271, 1270, 1270, 1270, 1270, 1272, 1273, 1274, 218, 218, 218,
		1275, 1276, 1277, 1277, 1277, 1277, 1278, 1279, 1280, 218, 1281, 1282, 1283, 1283, 1283, 1283,
		1283, 1284, 1285, 1286, 1287, 218, 387, 387, 1288, 1288, 1288, 1288, 1288, 1288, 1288, 1289, 1290,
		1291, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1292, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 1293, 1293, 1293, 1293, 1294, 218, 1295, 1296, 1297, 1298, 1297, 1297, 1297,
		1299, 1300, 1301, 1302, 218, 1303, 1304, 1305, 1306, 1307, 1308, 1308, 1308, 1309, 1310, 1310,
		1311, 1312, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1313, 1314, 1315, 1315, 1315, 1315, 1316,
		1317, 1318, 218, 1319, 1320, 1321, 1322, 1323, 1323, 1323, 1324, 1325, 1326, 1327, 1328, 1329,
		1329, 1329, 1329, 1329, 1330, 1331, 1332, 1333, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		1334, 1334, 1335, 1336, 1337, 1338, 1339, 1338, 1338, 1338, 1340, 1341, 1342, 1343, 1344, 1345,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1346, 218, 1347, 1347, 1348, 1349, 1350, 1351,
		1352, 1353, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354,
		1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354,
		1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354,
		1354, 1354, 1354, 1354, 1354, 1355, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1356, 1357, 1356,
		1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354,
		1354, 1354, 1354, 1354, 1354, 1354, 1354, 1354, 1358, 218, 1356, 1356, 1356, 1356, 1356, 1356,
		1356, 1356, 1356, 1356, 1356, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359,
		1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1359, 1360, 1356, 1356, 1356, 1356, 1356, 1356,
		1361, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 1362, 1362, 1362, 1362, 1362, 1362, 1362, 1362, 1362,
		1362, 1362, 1362, 1363, 218, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364,
		1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364,
		1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1365, 1365, 1366, 1367, 1368,
		218, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364,
		1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364,
		1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364, 1364,
		1364, 1364, 1364, 1364, 1369, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370,
		1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370,
		1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1370, 1371, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 1372, 1372, 1372, 1373, 1374, 1375, 1376, 1377, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 804, 804, 804, 804,
		804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804,
		804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 804, 1378, 1379, 1379,
		1379, 1380, 1381, 1382, 1383, 1383, 1383, 1383, 1383, 1383, 1383, 1383, 1383, 1384, 1385, 1386,
		1387, 1387, 1387, 1388, 1389, 218, 1390, 1390, 1390, 1390, 1390, 1390, 1391, 1392, 1393, 218,
		1394, 1395, 1396, 1390, 1390, 1397, 1390, 1390, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1398, 1399, 1399, 1399, 1399, 1400,
		1401, 1402, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 1403, 1403, 1403, 1403, 1404, 1404, 1404, 1404, 1405, 1405,
		1406, 1407, 1408, 1408, 1408, 1409, 1410, 1410, 1411, 218, 218, 218, 218, 218, 1412, 1412, 1412,
		1412, 1412, 1412, 1412, 1412, 1412, 1413, 1414, 1415, 1415, 1415, 1415, 1415, 1415, 1416, 1417,
		1418, 218, 218, 218, 218, 218, 218, 218, 218, 1419, 218, 1420, 218, 1421, 1421, 1421, 1421, 1421,
		1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421,
		1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1422, 1422, 1422, 1422, 1422,
		1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422,
		1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422,
		1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422, 1422,
		1422, 1422, 1422, 1422, 1422, 1422, 1423, 218, 218, 218, 1424, 1421, 1421, 1421, 1421, 1425, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1421, 1421, 1421, 1421, 1421, 1421, 1421, 1421,
		1421, 1421, 1421, 1421, 1421, 1421, 1426, 218, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427,
		1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1427, 1428, 218, 1427, 1427, 1427, 1427,
		1427, 1427, 1429, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		1430, 1431, 1432, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759,
		759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759, 759,
		1433, 1434, 1435, 218, 218, 218, 1436, 218, 1437, 1434, 1438, 1438, 1438, 1438, 1438, 1438, 1438,
		1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438,
		1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438,
		1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1438, 1439, 1440, 1440, 1440, 1440, 1440,
		1440, 1440, 1440, 1440, 1440, 1440, 1440, 1440, 1441, 1440, 1442, 1440, 1443, 1440, 1444, 1445,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 621,
		622, 622, 622, 1446, 1447, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 1448, 1449, 586, 586, 1450, 1451, 586, 586, 592, 1452, 1453, 1453, 1453, 1453, 1453,
		1454, 1453, 1453, 1455, 218, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		1456, 218, 218, 218, 218, 218, 218, 218, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622,
		1457, 218, 622, 622, 622, 622, 1458, 1459, 622, 622, 622, 622, 622, 622, 1460, 1461, 1462, 1463,
		1464, 1465, 622, 622, 622, 1466, 622, 622, 622, 622, 622, 622, 622, 1467, 622, 622, 964, 964, 964,
		964, 964, 964, 964, 964, 1468, 218, 1469, 1470, 622, 622, 622, 622, 1471, 218, 218, 218, 218, 218,
		218, 218, 959, 959, 1472, 218, 959, 959, 1472, 218, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 1473, 218, 775, 775, 1474, 1475, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 1476, 1476, 1476, 1477, 1478, 1478, 1479, 1476, 1476, 1480, 1481, 1478, 1478,
		1476, 1476, 1476, 1477, 1478, 1478, 1482, 1483, 1484, 1480, 1485, 1486, 1478, 1476, 1476, 1476,
		1477, 1478, 1478, 1487, 1488, 1489, 1490, 1478, 1478, 1478, 1491, 1492, 1493, 1494, 1478, 1478,
		1479, 1476, 1476, 1480, 1478, 1478, 1478, 1476, 1476, 1476, 1477, 1478, 1478, 1479, 1476, 1476,
		1480, 1478, 1478, 1478, 1476, 1476, 1476, 1477, 1478, 1478, 1479, 1476, 1476, 1480, 1478, 1478,
		1478, 1476, 1476, 1476, 1477, 1478, 1478, 1495, 1476, 1476, 1476, 1496, 1478, 1478, 1497, 1498,
		1476, 1476, 1499, 1478, 1478, 1500, 1479, 1476, 1476, 1501, 1478, 1478, 1502, 1503, 1476, 1476,
		1504, 1478, 1478, 1478, 1505, 1476, 1476, 1476, 1496, 1478, 1478, 1497, 1506, 1446, 1446, 1446,
		1446, 1446, 1446, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507,
		1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507, 1507,
		1507, 1507, 1507, 1508, 1508, 1508, 1508, 1508, 1508, 1509, 1510, 1508, 1508, 1508, 1508, 1508,
		1511, 1512, 1507, 1513, 1514, 218, 1515, 1516, 1508, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 1517, 694, 1518, 1519, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 54, 1520, 54, 54, 54, 54,
		54, 54, 1521, 722, 1522, 54, 54, 27, 811, 27, 1523, 218, 900, 218, 218, 218, 218, 218, 218, 1524,
		56, 56, 56, 56, 1525, 56, 1526, 1527, 1527, 1528, 1529, 1530, 1531, 1531, 1531, 1531, 1531, 1531,
		1531, 1532, 218, 218, 218, 1533, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 1534, 1534, 1534, 1534, 1534, 1535, 1536, 1537, 1538, 1539, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1540, 1540, 1540, 1541,
		218, 218, 1542, 1542, 1542, 1542, 1542, 1543, 1544, 1545, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1546,
		1546, 1546, 1547, 1548, 1549, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 1550, 1550, 1550, 1551, 1552,
		1553, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 1554, 1554, 1554, 1555, 1556, 1557, 1558, 1559, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 375, 1560, 373, 375, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561,
		1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561, 1561,
		1562, 1563, 1564, 123, 123, 123, 123, 123, 1565, 1565, 1565, 1565, 1566, 1567, 1567, 1567, 1568,
		1569, 1570, 1571, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123,
		123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123,
		123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123,
		123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 1572, 1573, 1573, 1573, 1573, 1573,
		1573, 1574, 1575, 154, 123, 123, 123, 123, 123, 123, 123, 123, 1572, 1573, 1573, 1573, 1573, 1576,
		1573, 1577, 154, 154, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123, 123,
		123, 123, 123, 123, 123, 123, 123, 1578, 128, 128, 128, 1579, 1580, 1581, 1582, 1583, 1584, 1579,
		1585, 1579, 1581, 1581, 1586, 128, 1587, 128, 1588, 1589, 1587, 128, 1588, 154, 154, 154, 154,
		154, 154, 1590, 154, 1591, 586, 586, 586, 586, 1448, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 1448, 218, 586, 1592, 1593, 586, 1593, 655, 1593, 586, 586, 586, 1594, 218, 633,
		1595, 635, 635, 635, 1596, 635, 635, 635, 635, 635, 635, 635, 1597, 635, 635, 635, 1598, 1599,
		1600, 635, 1601, 218, 218, 218, 218, 218, 218, 1602, 622, 622, 622, 1603, 218, 770, 770, 770, 770,
		770, 1604, 770, 1605, 1606, 218, 771, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 650, 650, 650, 650, 670, 1607, 1608, 650, 650, 650, 650, 650,
		650, 650, 650, 1609, 650, 650, 652, 586, 650, 650, 650, 650, 650, 1610, 652, 586, 650, 650, 1611,
		1612, 650, 650, 650, 650, 650, 650, 650, 1613, 1614, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 1615, 650, 650, 650, 650, 650,
		650, 650, 1616, 586, 1617, 650, 650, 650, 586, 586, 1618, 586, 586, 1619, 586, 1591, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 1620, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		586, 586, 586, 586, 586, 586, 650, 650, 650, 650, 650, 650, 650, 650, 1616, 1591, 1621, 1622, 586,
		1623, 1624, 1625, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 1626, 650, 1627, 670, 586, 586, 1448, 586,
		586, 586, 586, 586, 586, 586, 218, 586, 630, 586, 586, 586, 586, 586, 218, 586, 586, 586, 1594,
		586, 1448, 630, 218, 592, 1628, 218, 218, 218, 218, 586, 1624, 650, 650, 650, 650, 650, 1629,
		1608, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 650, 650, 650, 650, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 218, 586, 1594,
		650, 1625, 650, 650, 650, 650, 650, 650, 650, 650, 1473, 1630, 650, 1631, 650, 1632, 650, 1633,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 1634,
		586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 586, 1446, 1635, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		218, 218, 218, 218, 218, 218, 1636, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 899, 899, 899,
		899, 788, 788, 788, 1637, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 897, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 1638, 899,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 897, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 788, 788, 788, 897, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 1639, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 1640, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788, 788,
		788, 788, 788, 788, 788, 788, 898, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641,
		1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641,
		1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 1641, 899, 899, 899, 899, 899, 899,
		899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 899, 1642,
		914, 914, 914, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 1643, 914, 914,
		914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 916, 916, 916, 916, 916,
		916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916, 916,
		916, 916, 916, 916, 916, 916, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914,
		914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914, 914,
		914, 914, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896,
		896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 896, 1644,
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
		79, 79, 79, 79, 79, 79, 79, 79, 79, 79, 79, 0, 80, 80, 81, 81, 81, 81, 81, 81, 82, 82, 82, 82, 82,
		82, 82, 82, 82, 81, 83, 80, 80, 84, 84, 85, 86, 87, 88, 88, 88, 88, 87, 88, 88, 88, 89, 87, 88,
		88, 88, 88, 88, 88, 87, 87, 87, 87, 87, 87, 88, 88, 87, 88, 88, 89, 90, 88, 91, 92, 93, 94, 95,
		96, 97, 98, 99, 100, 100, 101, 102, 103, 104, 105, 106, 107, 108, 106, 88, 87, 106, 99, 91, 102,
		86, 86, 86, 86, 86, 86, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 86, 86, 86, 86,
		109, 109, 109, 109, 106, 106, 86, 86, 86, 86, 86, 86, 86, 86, 86, 86, 86, 110, 110, 110, 110, 110,
		111, 112, 112, 113, 114, 114, 115, 116, 117, 118, 118, 119, 119, 119, 119, 119, 119, 119, 119,
		120, 121, 122, 123, 124, 117, 117, 123, 125, 125, 125, 125, 125, 125, 125, 125, 126, 125, 125,
		125, 125, 125, 125, 125, 125, 125, 125, 127, 128, 129, 130, 131, 132, 133, 134, 77, 77, 135, 136,
		119, 119, 119, 119, 119, 136, 119, 119, 136, 137, 137, 137, 137, 137, 137, 137, 137, 137, 137,
		114, 138, 138, 117, 125, 125, 139, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 117,
		125, 119, 119, 119, 119, 119, 119, 119, 111, 118, 119, 119, 119, 119, 136, 119, 140, 140, 119,
		119, 118, 136, 119, 119, 136, 125, 125, 141, 141, 141, 141, 141, 141, 141, 141, 141, 141, 125,
		125, 125, 142, 142, 125, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143, 143,
		144, 145, 146, 147, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 146, 148,
		149, 148, 148, 149, 148, 148, 149, 149, 149, 148, 149, 149, 148, 149, 148, 148, 148, 149, 148,
		149, 148, 149, 148, 149, 148, 148, 144, 144, 146, 146, 146, 150, 150, 150, 150, 150, 150, 150,
		150, 150, 150, 150, 150, 150, 150, 151, 151, 151, 151, 151, 151, 151, 151, 151, 151, 151, 150,
		144, 144, 144, 144, 144, 144, 144, 144, 144, 144, 144, 144, 144, 144, 152, 152, 152, 152, 152,
		152, 152, 152, 152, 152, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153, 153,
		153, 153, 153, 154, 154, 154, 154, 154, 154, 154, 155, 154, 156, 156, 157, 158, 158, 158, 156, 86,
		86, 155, 159, 159, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 160, 161, 161,
		161, 161, 162, 161, 161, 161, 161, 161, 161, 161, 161, 161, 162, 161, 161, 161, 162, 161, 161,
		161, 161, 161, 86, 86, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163, 163,
		86, 164, 164, 164, 164, 164, 164, 164, 164, 164, 165, 165, 165, 86, 86, 166, 86, 146, 146, 146,
		144, 144, 144, 144, 144, 167, 125, 125, 125, 125, 125, 125, 125, 110, 110, 144, 144, 144, 144,
		144, 119, 119, 136, 136, 136, 119, 119, 119, 119, 125, 140, 119, 119, 119, 119, 119, 136, 136,
		136, 136, 136, 119, 119, 119, 119, 119, 119, 111, 136, 119, 119, 136, 119, 119, 136, 119, 119,
		119, 136, 136, 136, 168, 169, 170, 119, 119, 119, 136, 119, 119, 136, 136, 119, 119, 119, 119,
		119, 171, 171, 171, 172, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173, 173,
		171, 172, 174, 173, 172, 172, 172, 171, 171, 171, 171, 171, 171, 171, 171, 172, 172, 172, 172,
		175, 172, 172, 173, 77, 135, 77, 77, 171, 171, 171, 173, 173, 171, 171, 176, 176, 177, 177, 177,
		177, 177, 177, 177, 177, 177, 177, 178, 179, 173, 173, 173, 173, 173, 173, 180, 181, 182, 182, 0,
		180, 180, 180, 180, 180, 180, 180, 180, 0, 0, 180, 180, 0, 0, 180, 180, 180, 180, 180, 180, 180,
		180, 180, 180, 180, 180, 180, 180, 0, 180, 180, 180, 180, 180, 180, 180, 0, 180, 0, 0, 0, 180,
		180, 180, 180, 0, 0, 183, 180, 182, 182, 182, 181, 181, 181, 181, 0, 0, 182, 182, 0, 0, 182, 182,
		184, 180, 0, 0, 0, 0, 0, 0, 0, 0, 182, 0, 0, 0, 0, 180, 180, 0, 180, 180, 180, 181, 181, 0, 0,
		185, 185, 185, 185, 185, 185, 185, 185, 185, 185, 180, 180, 186, 186, 187, 187, 187, 187, 187,
		187, 188, 186, 180, 189, 190, 0, 0, 191, 191, 192, 0, 193, 193, 193, 193, 193, 193, 0, 0, 0, 0,
		193, 193, 0, 0, 193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 193, 0, 193, 193,
		193, 193, 193, 193, 193, 0, 193, 193, 0, 193, 193, 0, 193, 193, 0, 0, 194, 0, 192, 192, 192, 191,
		191, 0, 0, 0, 0, 191, 191, 0, 0, 191, 191, 195, 0, 0, 0, 191, 0, 0, 0, 0, 0, 0, 0, 193, 193, 193,
		193, 0, 193, 0, 0, 0, 0, 0, 0, 0, 196, 196, 196, 196, 196, 196, 196, 196, 196, 196, 191, 191, 193,
		193, 193, 191, 197, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 198, 198, 199, 0, 200, 200, 200, 200, 200, 200,
		200, 200, 200, 0, 200, 200, 200, 0, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200,
		200, 200, 0, 200, 200, 200, 200, 200, 200, 200, 0, 200, 200, 0, 200, 200, 200, 200, 200, 0, 0,
		201, 200, 199, 199, 199, 198, 198, 198, 198, 198, 0, 198, 198, 199, 0, 199, 199, 202, 0, 0, 200,
		0, 0, 0, 0, 0, 0, 0, 200, 200, 198, 198, 0, 0, 203, 203, 203, 203, 203, 203, 203, 203, 203, 203,
		204, 205, 0, 0, 0, 0, 0, 0, 0, 200, 198, 198, 198, 198, 198, 198, 0, 206, 207, 207, 0, 208, 208,
		208, 208, 208, 208, 208, 208, 0, 0, 208, 208, 0, 0, 208, 208, 208, 208, 208, 208, 208, 208, 208,
		208, 208, 208, 208, 208, 0, 208, 208, 208, 208, 208, 208, 208, 0, 208, 208, 0, 208, 208, 208, 208,
		208, 0, 0, 209, 208, 207, 206, 207, 206, 206, 206, 206, 0, 0, 207, 207, 0, 0, 207, 207, 210, 0, 0,
		0, 0, 0, 206, 206, 206, 206, 207, 0, 0, 0, 0, 208, 208, 0, 208, 208, 208, 206, 206, 0, 0, 211,
		211, 211, 211, 211, 211, 211, 211, 211, 211, 212, 208, 213, 213, 213, 213, 213, 213, 0, 0, 214,
		215, 0, 215, 215, 215, 215, 215, 215, 0, 0, 0, 215, 215, 215, 0, 215, 215, 215, 215, 0, 0, 0, 215,
		215, 0, 215, 0, 215, 215, 0, 0, 0, 215, 215, 0, 0, 0, 215, 215, 215, 215, 215, 215, 215, 215, 215,
		215, 0, 0, 0, 0, 216, 216, 214, 216, 216, 0, 0, 0, 216, 216, 216, 0, 216, 216, 216, 217, 0, 0,
		215, 0, 0, 0, 0, 0, 0, 216, 0, 0, 0, 0, 0, 0, 218, 218, 218, 218, 218, 218, 218, 218, 218, 218,
		219, 219, 219, 220, 220, 220, 220, 220, 220, 221, 220, 0, 0, 0, 0, 0, 222, 223, 223, 223, 222,
		224, 224, 224, 224, 224, 224, 224, 224, 0, 224, 224, 224, 0, 224, 224, 224, 224, 224, 224, 224,
		224, 224, 224, 224, 224, 224, 224, 224, 224, 0, 0, 225, 224, 222, 222, 222, 223, 223, 223, 223, 0,
		222, 222, 222, 0, 222, 222, 222, 226, 0, 0, 0, 0, 0, 0, 0, 227, 228, 0, 224, 224, 224, 0, 224,
		224, 0, 0, 224, 224, 222, 222, 0, 0, 229, 229, 229, 229, 229, 229, 229, 229, 229, 229, 0, 0, 0, 0,
		0, 0, 0, 230, 231, 231, 231, 231, 231, 231, 231, 232, 233, 234, 235, 235, 236, 233, 233, 233, 233,
		233, 233, 233, 233, 0, 233, 233, 233, 0, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233, 233,
		233, 233, 233, 233, 233, 233, 233, 0, 233, 233, 233, 233, 233, 0, 0, 237, 233, 235, 238, 235, 235,
		235, 235, 235, 0, 238, 235, 235, 0, 235, 235, 234, 239, 0, 0, 0, 0, 0, 0, 0, 235, 235, 0, 0, 0, 0,
		0, 233, 233, 233, 0, 233, 233, 234, 234, 0, 0, 240, 240, 240, 240, 240, 240, 240, 240, 240, 240,
		0, 233, 233, 235, 0, 0, 0, 0, 241, 241, 242, 242, 243, 243, 243, 243, 243, 243, 243, 243, 243, 0,
		243, 243, 243, 0, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243,
		243, 244, 244, 243, 242, 242, 242, 241, 241, 241, 241, 0, 242, 242, 242, 0, 242, 242, 242, 244,
		243, 245, 0, 0, 0, 0, 243, 243, 243, 242, 246, 246, 246, 246, 246, 246, 246, 243, 243, 243, 241,
		241, 0, 0, 247, 247, 247, 247, 247, 247, 247, 247, 247, 247, 246, 246, 246, 246, 246, 246, 246,
		246, 246, 245, 243, 243, 243, 243, 243, 243, 0, 248, 249, 249, 0, 250, 250, 250, 250, 250, 250,
		250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 250, 0, 0, 0, 250, 250, 250, 250, 250, 250,
		250, 250, 0, 250, 250, 250, 250, 250, 250, 250, 250, 250, 0, 250, 0, 0, 0, 0, 251, 0, 0, 0, 0,
		249, 249, 249, 248, 248, 248, 0, 248, 0, 249, 249, 249, 249, 249, 249, 249, 249, 0, 0, 0, 0, 0, 0,
		252, 252, 252, 252, 252, 252, 252, 252, 252, 252, 0, 0, 249, 249, 253, 0, 0, 0, 0, 254, 254, 254,
		254, 254, 254, 254, 254, 254, 254, 254, 254, 254, 254, 254, 254, 255, 254, 254, 255, 255, 255,
		255, 256, 256, 257, 0, 0, 0, 0, 258, 254, 254, 254, 254, 254, 254, 259, 255, 260, 260, 260, 260,
		255, 255, 255, 261, 262, 262, 262, 262, 262, 262, 262, 262, 262, 262, 261, 261, 0, 0, 0, 0, 0,
		263, 263, 0, 263, 0, 263, 263, 263, 263, 263, 0, 263, 263, 263, 263, 263, 263, 263, 263, 263, 263,
		263, 263, 263, 263, 263, 263, 0, 263, 0, 263, 263, 264, 263, 263, 264, 264, 264, 264, 265, 265,
		266, 264, 264, 263, 0, 0, 263, 263, 263, 263, 263, 0, 267, 0, 268, 268, 268, 268, 264, 264, 264,
		0, 269, 269, 269, 269, 269, 269, 269, 269, 269, 269, 0, 0, 263, 263, 263, 263, 270, 271, 271, 271,
		272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 272, 271, 272, 271, 271,
		271, 273, 273, 271, 271, 271, 271, 271, 271, 274, 274, 274, 274, 274, 274, 274, 274, 274, 274,
		275, 275, 275, 275, 275, 275, 275, 275, 275, 275, 271, 273, 271, 273, 271, 276, 277, 278, 277,
		278, 279, 279, 270, 270, 270, 270, 270, 270, 270, 270, 0, 270, 270, 270, 270, 270, 270, 270, 270,
		270, 270, 270, 270, 0, 0, 0, 0, 280, 281, 282, 283, 282, 282, 282, 282, 282, 281, 281, 281, 281,
		282, 279, 281, 282, 284, 284, 285, 272, 284, 284, 270, 270, 270, 270, 270, 282, 282, 282, 282,
		282, 282, 282, 282, 282, 282, 282, 0, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282, 282,
		0, 271, 271, 271, 271, 271, 271, 271, 271, 273, 271, 271, 271, 271, 271, 271, 0, 271, 271, 272,
		272, 272, 272, 272, 286, 286, 286, 286, 272, 272, 0, 0, 0, 0, 0, 287, 287, 287, 287, 287, 287,
		287, 287, 287, 287, 287, 288, 288, 289, 289, 289, 289, 288, 289, 289, 289, 289, 289, 290, 288,
		291, 291, 288, 288, 289, 289, 287, 292, 292, 292, 292, 292, 292, 292, 292, 292, 292, 293, 293,
		293, 293, 293, 293, 287, 287, 287, 287, 287, 287, 288, 288, 289, 289, 287, 287, 287, 287, 289,
		289, 289, 287, 288, 288, 288, 287, 287, 288, 288, 288, 288, 288, 288, 288, 287, 287, 287, 289,
		289, 289, 289, 287, 287, 287, 287, 287, 289, 288, 288, 289, 289, 288, 288, 288, 288, 288, 288,
		294, 287, 288, 292, 292, 288, 288, 288, 289, 295, 295, 296, 296, 296, 296, 296, 296, 296, 296,
		296, 296, 296, 296, 296, 296, 0, 296, 0, 0, 0, 0, 0, 296, 0, 0, 297, 297, 297, 297, 297, 297, 297,
		297, 297, 297, 297, 176, 298, 297, 297, 297, 299, 299, 299, 299, 299, 299, 299, 299, 300, 300,
		300, 300, 300, 300, 300, 300, 301, 301, 301, 301, 301, 301, 301, 301, 301, 0, 301, 301, 301, 301,
		0, 0, 301, 301, 301, 301, 301, 301, 301, 0, 301, 301, 301, 0, 0, 302, 302, 302, 303, 303, 303,
		303, 303, 303, 303, 303, 303, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304, 304,
		304, 304, 304, 304, 304, 304, 304, 0, 0, 0, 305, 305, 305, 305, 305, 305, 305, 305, 305, 305, 0,
		0, 0, 0, 0, 0, 306, 306, 306, 306, 306, 306, 306, 306, 306, 306, 306, 306, 306, 306, 0, 0, 307,
		307, 307, 307, 307, 307, 0, 0, 308, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309, 309,
		309, 309, 309, 309, 309, 309, 309, 309, 310, 311, 309, 312, 313, 313, 313, 313, 313, 313, 313,
		313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 313, 314, 315, 0, 0, 0, 316, 316, 316, 316, 316,
		316, 316, 316, 316, 316, 316, 176, 176, 176, 317, 317, 317, 316, 316, 316, 316, 316, 316, 316,
		316, 0, 0, 0, 0, 0, 0, 0, 318, 318, 318, 318, 318, 318, 318, 318, 318, 318, 319, 319, 320, 321, 0,
		0, 0, 0, 0, 0, 0, 0, 0, 318, 322, 322, 322, 322, 322, 322, 322, 322, 322, 322, 323, 323, 324, 176,
		176, 0, 325, 325, 325, 325, 325, 325, 325, 325, 325, 325, 326, 326, 0, 0, 0, 0, 327, 327, 327,
		327, 327, 327, 327, 327, 327, 327, 327, 327, 327, 0, 327, 327, 327, 0, 328, 328, 0, 0, 0, 0, 329,
		329, 329, 329, 329, 329, 329, 329, 329, 329, 329, 329, 330, 330, 331, 330, 330, 330, 330, 330,
		330, 330, 331, 331, 331, 331, 331, 331, 331, 331, 330, 331, 331, 330, 330, 330, 330, 330, 330,
		330, 330, 330, 332, 330, 333, 333, 333, 334, 333, 333, 333, 335, 329, 336, 0, 0, 337, 337, 337,
		337, 337, 337, 337, 337, 337, 337, 0, 0, 0, 0, 0, 0, 338, 338, 338, 338, 338, 338, 338, 338, 338,
		338, 0, 0, 0, 0, 0, 0, 339, 339, 65, 65, 339, 65, 340, 339, 339, 339, 339, 341, 341, 341, 342,
		341, 343, 343, 343, 343, 343, 343, 343, 343, 343, 343, 0, 0, 0, 0, 0, 0, 344, 344, 344, 344, 344,
		344, 344, 344, 344, 344, 344, 345, 344, 344, 344, 344, 344, 0, 0, 0, 0, 0, 0, 0, 344, 344, 344,
		344, 344, 341, 341, 344, 344, 346, 344, 0, 0, 0, 0, 0, 309, 309, 309, 309, 309, 309, 0, 0, 347,
		347, 347, 347, 347, 347, 347, 347, 347, 347, 347, 347, 347, 347, 347, 0, 348, 348, 348, 349, 349,
		349, 349, 348, 348, 349, 349, 349, 0, 0, 0, 0, 349, 349, 348, 349, 349, 349, 349, 349, 349, 350,
		351, 352, 0, 0, 0, 0, 353, 0, 0, 0, 354, 354, 355, 355, 355, 355, 355, 355, 355, 355, 355, 355,
		356, 356, 356, 356, 356, 356, 356, 356, 356, 356, 356, 356, 356, 356, 0, 0, 356, 356, 356, 356,
		356, 0, 0, 0, 357, 357, 357, 357, 357, 357, 357, 357, 357, 357, 357, 357, 0, 0, 0, 0, 357, 357, 0,
		0, 0, 0, 0, 0, 358, 358, 358, 358, 358, 358, 358, 358, 358, 358, 359, 0, 0, 0, 360, 360, 361, 361,
		361, 361, 361, 361, 361, 361, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362, 362,
		362, 362, 363, 364, 365, 365, 366, 0, 0, 367, 367, 368, 368, 368, 368, 368, 368, 368, 368, 368,
		368, 368, 368, 368, 369, 370, 369, 370, 370, 370, 370, 370, 370, 370, 0, 371, 369, 370, 369, 369,
		370, 370, 370, 370, 370, 370, 370, 370, 369, 369, 369, 369, 369, 369, 370, 370, 372, 372, 372,
		372, 372, 372, 372, 372, 0, 0, 373, 374, 374, 374, 374, 374, 374, 374, 374, 374, 374, 0, 0, 0, 0,
		0, 0, 375, 375, 375, 375, 375, 375, 375, 376, 375, 375, 375, 375, 375, 375, 0, 0, 77, 77, 77, 77,
		77, 135, 135, 135, 135, 135, 135, 77, 77, 135, 377, 135, 135, 77, 77, 135, 135, 77, 77, 77, 77,
		77, 135, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 135, 77, 77, 77,
		77, 77, 77, 77, 77, 135, 77, 77, 77, 77, 378, 77, 77, 135, 135, 77, 0, 0, 0, 0, 0, 0, 0, 379, 379,
		379, 379, 380, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 381, 382,
		380, 379, 379, 379, 379, 383, 380, 383, 380, 380, 380, 380, 380, 383, 380, 384, 381, 381, 381,
		381, 381, 381, 381, 381, 0, 385, 385, 386, 386, 386, 386, 386, 386, 386, 386, 386, 386, 385, 385,
		385, 385, 385, 385, 385, 387, 387, 387, 387, 387, 387, 387, 387, 387, 387, 388, 389, 388, 388,
		388, 388, 388, 388, 388, 387, 387, 387, 387, 387, 387, 387, 387, 387, 385, 385, 385, 390, 390,
		391, 392, 392, 392, 392, 392, 392, 392, 392, 392, 392, 392, 392, 392, 392, 391, 390, 390, 390,
		390, 391, 391, 390, 390, 393, 394, 390, 390, 392, 392, 395, 395, 395, 395, 395, 395, 395, 395,
		395, 395, 392, 392, 392, 392, 392, 392, 396, 396, 396, 396, 396, 396, 396, 396, 396, 396, 396,
		396, 396, 396, 397, 398, 399, 399, 398, 398, 398, 399, 398, 399, 399, 399, 400, 400, 0, 0, 0, 0,
		0, 0, 0, 0, 401, 401, 401, 401, 402, 402, 402, 402, 402, 402, 402, 402, 402, 402, 402, 402, 403,
		403, 403, 403, 403, 403, 403, 403, 404, 404, 404, 404, 404, 404, 404, 404, 403, 403, 404, 405, 0,
		0, 0, 406, 406, 406, 406, 406, 407, 407, 407, 407, 407, 407, 407, 407, 407, 407, 0, 0, 0, 402,
		402, 402, 408, 408, 408, 408, 408, 408, 408, 408, 408, 408, 409, 409, 409, 409, 409, 409, 409,
		409, 409, 409, 409, 409, 409, 409, 410, 410, 410, 410, 410, 410, 411, 411, 74, 71, 74, 0, 0, 0, 0,
		0, 296, 296, 296, 0, 0, 296, 296, 296, 412, 412, 412, 412, 412, 412, 412, 412, 77, 77, 77, 176,
		413, 135, 135, 135, 135, 135, 77, 77, 135, 135, 135, 135, 77, 414, 413, 413, 413, 413, 413, 413,
		413, 415, 415, 415, 415, 135, 415, 415, 415, 415, 415, 415, 77, 415, 415, 414, 77, 77, 415, 0, 0,
		0, 0, 0, 41, 41, 41, 41, 41, 41, 62, 62, 62, 62, 62, 74, 44, 44, 44, 44, 44, 44, 44, 44, 44, 64,
		64, 64, 64, 64, 44, 44, 44, 44, 64, 64, 64, 64, 64, 41, 41, 41, 41, 41, 416, 41, 41, 41, 41, 41,
		41, 41, 41, 41, 41, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 44, 64, 77, 77, 135, 77, 77, 378,
		417, 135, 418, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 77, 419, 420, 420, 135, 421, 77,
		422, 135, 77, 135, 37, 41, 37, 41, 37, 41, 41, 41, 41, 41, 41, 41, 41, 41, 37, 41, 62, 62, 62, 62,
		62, 62, 62, 62, 61, 61, 61, 61, 61, 61, 61, 61, 62, 62, 62, 62, 62, 62, 0, 0, 61, 61, 61, 61, 61,
		61, 0, 0, 0, 61, 0, 61, 0, 61, 0, 61, 423, 423, 423, 423, 423, 423, 423, 423, 62, 62, 62, 62, 62,
		0, 62, 62, 61, 61, 61, 61, 423, 63, 62, 63, 63, 63, 62, 62, 62, 0, 62, 62, 61, 61, 61, 61, 423,
		63, 63, 63, 62, 62, 62, 62, 0, 0, 62, 62, 61, 61, 61, 61, 0, 63, 63, 63, 61, 61, 61, 61, 61, 63,
		63, 63, 0, 0, 62, 62, 62, 0, 62, 62, 61, 61, 61, 61, 423, 63, 63, 0, 424, 424, 424, 424, 424, 424,
		424, 424, 424, 424, 424, 425, 426, 426, 427, 428, 429, 430, 430, 429, 429, 429, 22, 65, 431, 432,
		433, 434, 431, 432, 433, 434, 22, 22, 22, 65, 22, 22, 22, 22, 435, 436, 437, 438, 439, 440, 441,
		21, 442, 443, 442, 442, 443, 22, 65, 65, 65, 28, 35, 22, 65, 65, 22, 444, 444, 65, 65, 65, 445,
		446, 447, 65, 65, 65, 65, 65, 65, 65, 65, 65, 65, 65, 448, 65, 444, 65, 65, 65, 65, 65, 65, 65,
		65, 65, 65, 424, 425, 425, 425, 425, 425, 449, 450, 450, 450, 450, 425, 425, 425, 425, 425, 425,
		451, 44, 0, 0, 33, 451, 451, 451, 451, 451, 452, 452, 448, 446, 447, 453, 451, 33, 33, 33, 33,
		451, 451, 451, 451, 451, 452, 452, 448, 446, 447, 45, 258, 258, 258, 258, 258, 258, 258, 258, 258,
		454, 258, 258, 23, 258, 258, 258, 258, 258, 258, 258, 258, 455, 455, 455, 455, 455, 455, 455, 455,
		455, 455, 455, 77, 77, 413, 413, 77, 77, 77, 77, 413, 413, 413, 77, 77, 377, 377, 377, 377, 77,
		377, 377, 377, 413, 413, 77, 135, 77, 413, 413, 135, 135, 135, 135, 26, 26, 456, 30, 26, 30, 26,
		456, 26, 30, 34, 456, 456, 456, 34, 34, 456, 456, 456, 457, 26, 456, 30, 26, 448, 456, 456, 456,
		456, 456, 26, 26, 26, 30, 30, 26, 456, 26, 66, 26, 456, 26, 37, 38, 456, 456, 458, 34, 456, 456,
		37, 456, 34, 415, 415, 415, 415, 34, 26, 26, 34, 34, 456, 456, 459, 448, 448, 448, 448, 456, 34,
		34, 34, 34, 26, 448, 26, 26, 41, 286, 460, 460, 460, 36, 36, 460, 460, 460, 460, 460, 460, 36, 36,
		36, 36, 460, 461, 461, 461, 461, 461, 461, 461, 461, 461, 461, 461, 461, 462, 462, 462, 462, 461,
		461, 462, 462, 462, 462, 462, 462, 462, 462, 462, 37, 41, 462, 462, 462, 462, 36, 26, 26, 0, 0, 0,
		0, 39, 39, 39, 39, 39, 30, 30, 30, 30, 30, 448, 448, 26, 26, 26, 26, 448, 26, 26, 448, 26, 26,
		448, 26, 26, 26, 26, 26, 26, 26, 448, 26, 26, 26, 26, 26, 26, 26, 26, 26, 30, 30, 26, 26, 26, 26,
		26, 26, 26, 26, 26, 26, 26, 26, 448, 448, 26, 26, 39, 26, 39, 26, 26, 26, 26, 26, 26, 26, 26, 26,
		26, 30, 26, 26, 26, 26, 448, 448, 448, 448, 448, 448, 448, 448, 448, 448, 448, 448, 39, 459, 463,
		463, 459, 448, 448, 39, 463, 459, 459, 463, 459, 459, 448, 39, 448, 463, 452, 464, 448, 463, 459,
		448, 448, 448, 463, 459, 459, 463, 39, 463, 463, 459, 459, 39, 459, 39, 459, 39, 39, 39, 39, 463,
		463, 459, 463, 459, 459, 459, 459, 459, 39, 39, 39, 39, 448, 459, 448, 459, 463, 463, 459, 459,
		459, 459, 459, 459, 459, 459, 459, 459, 463, 459, 459, 459, 463, 448, 448, 448, 448, 448, 463,
		459, 459, 459, 448, 448, 448, 448, 448, 448, 448, 448, 448, 459, 463, 39, 459, 448, 463, 463, 463,
		463, 459, 459, 463, 463, 448, 459, 463, 463, 459, 459, 463, 463, 459, 459, 463, 463, 459, 459,
		459, 459, 459, 448, 448, 459, 459, 459, 459, 448, 448, 39, 448, 448, 459, 39, 448, 448, 448, 448,
		448, 448, 448, 448, 459, 459, 448, 39, 459, 459, 459, 448, 448, 448, 448, 448, 459, 463, 448, 459,
		459, 459, 459, 459, 448, 448, 459, 459, 448, 448, 448, 448, 459, 459, 459, 459, 459, 459, 459,
		459, 448, 448, 446, 447, 446, 447, 26, 26, 26, 26, 26, 26, 30, 26, 26, 26, 26, 26, 26, 26, 465,
		465, 26, 26, 26, 26, 459, 459, 26, 26, 26, 26, 26, 26, 26, 466, 467, 26, 26, 26, 26, 26, 26, 26,
		26, 26, 26, 26, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 26, 448, 26, 26,
		26, 26, 26, 26, 26, 26, 286, 26, 26, 26, 26, 26, 448, 448, 448, 448, 448, 448, 448, 448, 448, 26,
		26, 26, 26, 448, 448, 26, 26, 26, 26, 26, 26, 26, 465, 465, 465, 465, 26, 26, 26, 465, 26, 26,
		465, 26, 26, 26, 26, 26, 26, 0, 0, 0, 0, 0, 0, 26, 26, 26, 0, 0, 0, 0, 0, 36, 36, 36, 36, 36, 36,
		36, 36, 33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 468, 468, 468, 468, 468, 468, 468, 468,
		468, 468, 468, 468, 468, 468, 460, 36, 36, 36, 36, 36, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
		30, 26, 26, 26, 26, 26, 26, 30, 30, 30, 30, 26, 26, 30, 30, 26, 30, 30, 30, 30, 30, 26, 26, 30,
		30, 26, 26, 30, 39, 26, 26, 26, 26, 30, 30, 26, 26, 30, 39, 26, 26, 26, 26, 30, 30, 30, 26, 26,
		30, 26, 26, 30, 30, 448, 448, 448, 448, 448, 469, 469, 448, 26, 26, 26, 26, 26, 30, 30, 26, 26,
		30, 26, 26, 26, 26, 30, 30, 26, 26, 26, 26, 465, 465, 26, 26, 26, 26, 26, 26, 30, 26, 30, 26, 465,
		465, 465, 465, 465, 465, 465, 465, 30, 26, 30, 26, 26, 26, 26, 26, 465, 465, 465, 465, 26, 26, 26,
		26, 30, 30, 26, 30, 30, 30, 26, 30, 30, 30, 30, 26, 30, 30, 26, 39, 26, 26, 26, 26, 26, 26, 26,
		465, 26, 26, 465, 465, 465, 465, 465, 465, 26, 26, 26, 465, 26, 26, 26, 26, 26, 26, 26, 26, 26,
		26, 30, 30, 26, 465, 26, 26, 26, 26, 26, 26, 26, 26, 465, 465, 286, 26, 26, 26, 26, 26, 26, 26,
		26, 465, 465, 30, 26, 26, 26, 26, 465, 465, 30, 30, 30, 30, 30, 30, 30, 30, 465, 30, 30, 30, 30,
		30, 465, 30, 30, 30, 30, 30, 26, 30, 26, 26, 26, 26, 30, 30, 465, 30, 30, 30, 30, 30, 30, 30, 465,
		465, 30, 465, 30, 30, 30, 30, 465, 30, 30, 465, 30, 30, 26, 26, 26, 26, 26, 465, 26, 26, 465, 26,
		26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 30, 26, 26, 26, 26, 26, 26, 465, 26, 465, 26, 26, 26,
		26, 465, 465, 465, 26, 465, 446, 447, 446, 447, 446, 447, 446, 447, 446, 447, 446, 447, 446, 447,
		36, 36, 460, 460, 460, 460, 460, 460, 460, 460, 460, 460, 460, 460, 26, 465, 465, 465, 459, 448,
		448, 459, 459, 446, 447, 448, 459, 459, 448, 459, 459, 459, 448, 448, 448, 448, 448, 459, 459,
		459, 459, 448, 448, 448, 448, 448, 459, 459, 459, 448, 448, 448, 459, 459, 459, 459, 9, 10, 9, 10,
		9, 10, 9, 10, 446, 447, 470, 470, 470, 470, 470, 470, 470, 470, 448, 448, 448, 446, 447, 9, 10,
		446, 447, 446, 447, 446, 447, 446, 447, 446, 447, 448, 448, 459, 459, 459, 459, 459, 459, 448,
		459, 459, 459, 459, 459, 459, 459, 448, 448, 448, 448, 448, 448, 448, 448, 459, 448, 448, 448,
		448, 459, 459, 459, 459, 459, 448, 459, 459, 448, 448, 446, 447, 446, 447, 459, 448, 448, 448,
		448, 459, 448, 459, 459, 459, 448, 448, 459, 459, 448, 448, 448, 448, 448, 448, 448, 448, 448,
		448, 459, 459, 459, 459, 459, 459, 448, 448, 446, 447, 448, 448, 448, 448, 459, 459, 459, 459,
		459, 459, 459, 459, 459, 459, 459, 448, 459, 459, 459, 459, 448, 448, 459, 448, 459, 448, 448,
		459, 448, 459, 459, 459, 459, 448, 448, 448, 448, 448, 459, 459, 448, 448, 448, 448, 459, 459,
		459, 459, 448, 459, 459, 448, 448, 459, 459, 448, 448, 448, 448, 459, 459, 459, 459, 459, 459,
		459, 459, 459, 459, 459, 448, 448, 459, 459, 459, 459, 459, 459, 459, 459, 448, 459, 459, 459,
		459, 459, 459, 459, 459, 448, 448, 448, 448, 448, 459, 448, 459, 448, 448, 448, 459, 459, 459,
		459, 459, 448, 448, 448, 448, 459, 448, 448, 448, 459, 459, 459, 459, 459, 448, 459, 448, 448, 26,
		26, 26, 465, 465, 26, 26, 26, 448, 448, 448, 448, 448, 26, 26, 448, 448, 448, 448, 448, 448, 26,
		26, 26, 465, 26, 26, 26, 26, 465, 30, 30, 26, 26, 26, 26, 0, 0, 26, 26, 26, 26, 26, 26, 26, 26,
		471, 26, 472, 472, 472, 472, 472, 472, 472, 472, 473, 473, 473, 473, 473, 473, 473, 473, 37, 41,
		37, 37, 37, 41, 41, 37, 41, 37, 41, 37, 41, 37, 37, 37, 37, 41, 37, 41, 41, 37, 41, 41, 41, 41,
		41, 41, 44, 44, 37, 37, 68, 69, 68, 69, 69, 474, 474, 474, 474, 474, 474, 68, 69, 68, 69, 475,
		475, 475, 68, 69, 0, 0, 0, 0, 0, 476, 476, 476, 476, 477, 476, 476, 297, 297, 297, 297, 297, 297,
		0, 297, 0, 0, 0, 0, 0, 297, 0, 0, 478, 478, 478, 478, 478, 478, 478, 478, 0, 0, 0, 0, 0, 0, 0,
		479, 480, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 481, 76, 76, 76, 76, 76, 76, 76, 76, 65, 65,
		28, 35, 28, 35, 65, 65, 65, 28, 35, 65, 28, 35, 65, 65, 65, 65, 65, 65, 65, 65, 65, 430, 65, 65,
		430, 65, 28, 35, 65, 65, 28, 35, 446, 447, 446, 447, 446, 447, 446, 447, 65, 65, 65, 65, 65, 45,
		65, 65, 430, 430, 65, 65, 65, 65, 430, 65, 433, 65, 65, 65, 65, 65, 26, 26, 65, 65, 65, 446, 447,
		446, 447, 446, 447, 446, 447, 430, 0, 0, 65, 65, 446, 447, 0, 0, 0, 0, 482, 482, 482, 482, 482,
		482, 482, 482, 482, 482, 0, 482, 482, 482, 482, 482, 482, 482, 482, 482, 0, 0, 0, 0, 482, 482,
		482, 482, 482, 482, 0, 0, 483, 484, 484, 484, 465, 485, 486, 487, 466, 467, 466, 467, 466, 467,
		466, 467, 466, 467, 465, 465, 466, 467, 466, 467, 466, 467, 466, 467, 488, 489, 490, 490, 465,
		487, 487, 487, 487, 487, 487, 487, 487, 487, 491, 492, 493, 494, 495, 495, 488, 496, 496, 496,
		496, 496, 465, 465, 487, 487, 487, 485, 486, 484, 465, 26, 0, 497, 497, 497, 497, 497, 497, 497,
		497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 497, 0, 0, 498, 498, 499,
		499, 500, 500, 497, 488, 501, 501, 501, 501, 501, 501, 501, 501, 501, 501, 501, 501, 501, 501,
		501, 501, 501, 501, 484, 496, 502, 502, 501, 0, 0, 0, 0, 0, 503, 503, 503, 503, 503, 503, 503,
		503, 503, 503, 503, 0, 299, 299, 299, 299, 299, 299, 299, 299, 299, 299, 299, 299, 299, 299, 0,
		504, 504, 505, 505, 505, 505, 504, 504, 504, 504, 504, 504, 504, 504, 504, 504, 465, 465, 465,
		465, 465, 465, 0, 0, 0, 0, 0, 0, 0, 0, 0, 465, 506, 506, 506, 506, 506, 506, 506, 506, 506, 506,
		506, 506, 506, 507, 507, 0, 505, 505, 505, 505, 505, 505, 505, 505, 505, 505, 504, 504, 504, 504,
		504, 504, 508, 508, 508, 508, 508, 508, 508, 508, 465, 509, 509, 509, 509, 509, 509, 509, 509,
		509, 509, 509, 509, 509, 509, 509, 506, 506, 506, 506, 507, 507, 507, 504, 504, 509, 509, 509,
		509, 509, 509, 509, 504, 504, 504, 504, 465, 465, 465, 465, 510, 510, 510, 510, 510, 510, 510,
		510, 510, 510, 510, 510, 510, 510, 510, 504, 504, 504, 504, 504, 504, 504, 504, 465, 465, 465,
		465, 504, 504, 504, 504, 504, 504, 504, 504, 504, 504, 504, 465, 465, 511, 511, 511, 511, 511,
		511, 511, 511, 512, 512, 512, 512, 512, 512, 512, 512, 512, 512, 512, 512, 512, 513, 512, 512,
		512, 512, 512, 512, 512, 0, 0, 0, 514, 514, 514, 514, 514, 514, 514, 514, 514, 514, 514, 514, 514,
		514, 514, 0, 515, 515, 515, 515, 515, 515, 515, 515, 516, 516, 516, 516, 516, 516, 517, 517, 518,
		518, 518, 518, 518, 518, 518, 518, 518, 518, 518, 518, 519, 520, 520, 520, 521, 521, 521, 521,
		521, 521, 521, 521, 521, 521, 518, 518, 0, 0, 0, 0, 71, 74, 71, 74, 71, 74, 522, 76, 78, 78, 78,
		523, 76, 76, 76, 76, 76, 76, 76, 76, 76, 76, 523, 524, 71, 74, 71, 74, 416, 416, 76, 76, 525, 525,
		525, 525, 525, 525, 525, 525, 525, 525, 525, 525, 525, 525, 526, 526, 526, 526, 526, 526, 526,
		526, 526, 526, 527, 527, 528, 528, 528, 528, 528, 528, 47, 47, 47, 47, 47, 47, 47, 45, 45, 45, 45,
		45, 45, 45, 45, 45, 47, 47, 37, 41, 37, 41, 37, 41, 41, 41, 37, 41, 37, 41, 37, 41, 44, 41, 41,
		41, 41, 41, 41, 41, 41, 37, 41, 37, 41, 37, 37, 41, 45, 529, 529, 37, 41, 37, 41, 42, 37, 41, 37,
		41, 41, 41, 37, 41, 37, 41, 37, 37, 37, 37, 37, 41, 37, 37, 37, 37, 37, 41, 37, 41, 37, 41, 37,
		41, 37, 37, 37, 37, 41, 37, 41, 37, 37, 41, 37, 41, 37, 41, 37, 41, 37, 37, 0, 0, 0, 0, 37, 0, 0,
		0, 0, 0, 0, 44, 44, 44, 44, 37, 41, 42, 44, 44, 41, 42, 42, 42, 42, 42, 530, 530, 531, 530, 530,
		530, 532, 530, 530, 530, 530, 531, 530, 530, 530, 530, 530, 530, 530, 530, 530, 530, 530, 530,
		530, 530, 530, 533, 533, 531, 531, 533, 534, 534, 534, 534, 532, 0, 0, 0, 535, 535, 535, 535, 535,
		535, 286, 286, 258, 458, 0, 0, 0, 0, 0, 0, 536, 536, 536, 536, 536, 536, 536, 536, 536, 536, 536,
		536, 537, 537, 537, 537, 538, 538, 539, 539, 539, 539, 539, 539, 539, 539, 539, 539, 539, 539,
		539, 539, 539, 539, 539, 539, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538,
		538, 538, 538, 540, 541, 0, 0, 0, 0, 0, 0, 0, 0, 542, 542, 543, 543, 543, 543, 543, 543, 543, 543,
		543, 543, 0, 0, 0, 0, 0, 0, 544, 544, 544, 544, 544, 544, 544, 544, 544, 544, 173, 173, 173, 173,
		173, 173, 178, 178, 178, 173, 178, 173, 173, 171, 545, 545, 545, 545, 545, 545, 545, 545, 545,
		545, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546, 546,
		546, 546, 547, 547, 547, 547, 547, 548, 548, 548, 176, 549, 550, 550, 550, 550, 550, 550, 550,
		550, 550, 550, 550, 550, 550, 550, 550, 551, 551, 551, 551, 551, 551, 551, 551, 551, 551, 551,
		552, 553, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 554, 299, 299, 299, 299, 299, 0, 0, 0, 555, 555, 555,
		556, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 557, 558, 556, 556,
		555, 555, 555, 555, 556, 556, 555, 555, 556, 556, 559, 560, 560, 560, 560, 560, 560, 560, 560,
		560, 560, 560, 560, 560, 0, 46, 561, 561, 561, 561, 561, 561, 561, 561, 561, 561, 0, 0, 0, 0, 560,
		560, 287, 287, 287, 287, 287, 289, 562, 287, 292, 292, 287, 287, 287, 287, 287, 0, 563, 563, 563,
		563, 563, 563, 563, 563, 563, 564, 564, 564, 564, 564, 564, 565, 565, 564, 564, 565, 565, 564,
		564, 0, 563, 563, 563, 564, 563, 563, 563, 563, 563, 563, 563, 563, 564, 565, 0, 0, 566, 566, 566,
		566, 566, 566, 566, 566, 566, 566, 0, 0, 567, 567, 567, 567, 562, 287, 287, 287, 287, 287, 287,
		295, 295, 295, 287, 288, 289, 288, 287, 287, 568, 568, 568, 568, 568, 568, 568, 568, 569, 568,
		569, 569, 570, 568, 568, 569, 569, 568, 568, 568, 568, 568, 569, 569, 568, 569, 568, 0, 0, 0, 0,
		0, 0, 0, 0, 568, 568, 571, 572, 572, 573, 573, 573, 573, 573, 573, 573, 573, 573, 573, 573, 574,
		575, 575, 574, 574, 576, 576, 573, 577, 577, 574, 578, 0, 0, 301, 301, 301, 301, 301, 301, 0, 41,
		41, 41, 529, 44, 44, 44, 44, 41, 41, 41, 41, 41, 62, 41, 41, 41, 44, 47, 47, 37, 37, 0, 0, 307,
		307, 307, 307, 307, 307, 307, 307, 573, 573, 573, 574, 574, 575, 574, 574, 575, 574, 574, 576,
		574, 578, 0, 0, 579, 579, 579, 579, 579, 579, 579, 579, 579, 579, 0, 0, 0, 0, 0, 0, 299, 299, 299,
		299, 0, 0, 0, 0, 300, 300, 300, 300, 300, 300, 300, 0, 0, 0, 0, 300, 300, 300, 300, 300, 300, 300,
		300, 300, 0, 0, 0, 0, 580, 580, 580, 580, 580, 580, 580, 580, 581, 581, 581, 581, 581, 581, 581,
		581, 511, 511, 511, 511, 511, 511, 582, 582, 511, 511, 582, 582, 582, 582, 582, 582, 582, 582,
		582, 582, 582, 582, 582, 582, 41, 41, 41, 41, 41, 41, 41, 0, 0, 0, 0, 82, 82, 82, 82, 82, 0, 0, 0,
		0, 0, 109, 583, 109, 109, 584, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109, 109,
		86, 109, 109, 109, 109, 109, 86, 109, 86, 109, 109, 86, 109, 109, 86, 109, 109, 125, 125, 167,
		167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 167, 118, 118, 118,
		118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 118, 125, 125, 125, 125, 125, 125,
		125, 125, 125, 125, 125, 585, 433, 118, 118, 125, 125, 125, 125, 125, 125, 449, 449, 449, 449,
		449, 449, 449, 449, 125, 125, 125, 125, 115, 118, 118, 118, 58, 58, 58, 58, 58, 58, 58, 58, 484,
		484, 484, 484, 484, 484, 484, 489, 490, 484, 0, 0, 0, 0, 0, 0, 77, 77, 77, 77, 77, 77, 77, 135,
		135, 135, 135, 135, 135, 135, 76, 76, 484, 488, 488, 586, 586, 489, 490, 489, 490, 489, 490, 489,
		490, 489, 490, 489, 490, 489, 490, 489, 490, 484, 484, 489, 490, 484, 484, 484, 484, 586, 586,
		586, 587, 484, 587, 0, 484, 587, 484, 484, 488, 466, 467, 466, 467, 466, 467, 588, 484, 484, 589,
		590, 591, 591, 469, 0, 484, 592, 588, 484, 0, 0, 0, 0, 125, 125, 125, 125, 125, 144, 125, 125,
		125, 125, 125, 125, 125, 144, 144, 425, 0, 593, 593, 594, 595, 594, 593, 593, 596, 597, 593, 598,
		599, 600, 599, 599, 601, 601, 601, 601, 601, 601, 601, 601, 601, 601, 599, 593, 602, 603, 602,
		593, 593, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604, 604,
		604, 596, 593, 597, 605, 606, 605, 607, 607, 607, 607, 607, 607, 607, 607, 607, 607, 607, 607,
		607, 607, 607, 607, 607, 607, 596, 603, 597, 603, 596, 597, 608, 609, 610, 608, 608, 611, 611,
		611, 611, 611, 611, 611, 611, 611, 611, 612, 611, 611, 611, 611, 611, 611, 611, 611, 611, 611,
		611, 611, 611, 612, 612, 613, 613, 613, 613, 613, 613, 613, 613, 613, 613, 613, 613, 613, 613,
		613, 0, 0, 0, 613, 613, 613, 613, 613, 613, 0, 0, 613, 613, 613, 0, 0, 0, 595, 595, 603, 605, 614,
		595, 595, 0, 615, 616, 616, 616, 616, 615, 615, 0, 449, 450, 450, 450, 26, 30, 449, 449, 617, 617,
		617, 617, 617, 617, 617, 617, 617, 617, 617, 617, 0, 617, 617, 617, 617, 617, 617, 617, 617, 617,
		617, 0, 617, 617, 617, 0, 617, 617, 0, 617, 617, 617, 617, 617, 617, 617, 0, 0, 617, 617, 617, 0,
		0, 0, 0, 0, 176, 65, 176, 0, 0, 0, 0, 535, 535, 535, 535, 535, 535, 535, 535, 535, 535, 535, 535,
		535, 0, 0, 0, 286, 618, 618, 618, 618, 618, 618, 618, 618, 618, 618, 618, 618, 618, 619, 619, 619,
		619, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 620, 619,
		619, 620, 621, 621, 0, 26, 26, 26, 26, 26, 0, 0, 0, 620, 0, 0, 0, 0, 0, 0, 0, 286, 286, 286, 286,
		286, 135, 0, 0, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 622, 0, 0, 0, 623,
		623, 623, 623, 623, 623, 623, 623, 623, 0, 0, 0, 0, 0, 0, 0, 135, 451, 451, 451, 451, 451, 451,
		451, 451, 451, 451, 451, 451, 451, 451, 451, 451, 451, 451, 451, 0, 0, 0, 0, 624, 624, 624, 624,
		624, 624, 624, 624, 625, 625, 625, 625, 0, 0, 0, 0, 0, 0, 0, 0, 0, 624, 624, 624, 626, 626, 626,
		626, 626, 626, 626, 626, 626, 627, 626, 626, 626, 626, 626, 626, 626, 626, 627, 0, 0, 0, 0, 0,
		628, 628, 628, 628, 628, 628, 628, 628, 628, 628, 628, 628, 628, 628, 629, 629, 629, 629, 629, 0,
		0, 0, 0, 0, 630, 630, 630, 630, 630, 630, 630, 630, 630, 630, 630, 630, 630, 630, 0, 631, 632,
		632, 632, 632, 632, 632, 632, 632, 632, 632, 632, 632, 0, 0, 0, 0, 633, 634, 634, 634, 634, 634,
		0, 0, 635, 635, 635, 635, 635, 635, 635, 635, 636, 636, 636, 636, 636, 636, 636, 636, 637, 637,
		637, 637, 637, 637, 637, 637, 638, 638, 638, 638, 638, 638, 638, 638, 638, 638, 638, 638, 638,
		638, 0, 0, 639, 639, 639, 639, 639, 639, 639, 639, 639, 639, 0, 0, 0, 0, 0, 0, 640, 640, 640, 640,
		640, 640, 640, 640, 640, 640, 640, 640, 0, 0, 0, 0, 641, 641, 641, 641, 641, 641, 641, 641, 641,
		641, 641, 641, 0, 0, 0, 0, 642, 642, 642, 642, 642, 642, 642, 642, 643, 643, 643, 643, 643, 643,
		643, 643, 643, 643, 643, 643, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 644, 645, 645, 645, 645, 645, 645,
		645, 645, 645, 645, 645, 0, 645, 645, 645, 645, 645, 645, 645, 0, 645, 645, 0, 646, 646, 646, 646,
		646, 646, 646, 646, 646, 646, 646, 0, 646, 646, 646, 646, 646, 646, 646, 0, 646, 646, 0, 0, 0,
		647, 647, 647, 647, 647, 647, 647, 647, 647, 647, 647, 647, 0, 0, 0, 0, 648, 648, 648, 648, 648,
		648, 648, 648, 648, 648, 648, 648, 648, 648, 648, 0, 648, 648, 648, 648, 648, 648, 0, 0, 44, 44,
		44, 44, 44, 44, 0, 44, 44, 0, 44, 44, 44, 44, 44, 44, 649, 649, 649, 649, 649, 649, 86, 86, 649,
		86, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649, 649,
		649, 86, 649, 649, 86, 86, 86, 649, 86, 86, 649, 650, 650, 650, 650, 650, 650, 650, 650, 650, 650,
		650, 650, 650, 650, 86, 651, 652, 652, 652, 652, 652, 652, 652, 652, 653, 653, 653, 653, 653, 653,
		653, 653, 653, 653, 653, 653, 653, 653, 653, 654, 654, 655, 655, 655, 655, 655, 655, 655, 656,
		656, 656, 656, 656, 656, 656, 656, 656, 656, 656, 656, 656, 656, 656, 86, 86, 86, 86, 86, 86, 86,
		86, 657, 657, 657, 657, 657, 657, 657, 657, 657, 658, 658, 658, 658, 658, 658, 658, 658, 658, 658,
		658, 86, 658, 658, 86, 86, 86, 86, 86, 659, 659, 659, 659, 659, 660, 660, 660, 660, 660, 660, 660,
		660, 660, 660, 660, 660, 660, 660, 661, 661, 661, 661, 661, 661, 86, 86, 86, 662, 663, 663, 663,
		663, 663, 663, 663, 663, 663, 663, 86, 86, 86, 86, 86, 664, 665, 665, 665, 665, 665, 665, 665,
		665, 665, 665, 86, 86, 86, 86, 86, 86, 666, 666, 666, 666, 666, 666, 666, 666, 667, 667, 667, 667,
		667, 667, 667, 667, 86, 86, 86, 86, 668, 668, 667, 667, 668, 668, 668, 668, 668, 668, 668, 668,
		86, 86, 668, 668, 668, 668, 668, 668, 669, 670, 670, 670, 86, 670, 670, 86, 86, 86, 86, 86, 670,
		671, 670, 672, 669, 669, 669, 669, 86, 669, 669, 669, 86, 669, 669, 669, 669, 669, 669, 669, 669,
		669, 669, 669, 669, 669, 669, 669, 669, 669, 669, 669, 669, 669, 86, 86, 672, 673, 671, 86, 86,
		86, 86, 674, 675, 675, 675, 675, 675, 675, 675, 675, 675, 86, 86, 86, 86, 86, 86, 86, 676, 676,
		676, 676, 676, 676, 676, 676, 676, 86, 86, 86, 86, 86, 86, 86, 677, 677, 677, 677, 677, 677, 677,
		677, 677, 677, 677, 677, 677, 678, 678, 679, 680, 680, 680, 680, 680, 680, 680, 680, 680, 680,
		680, 680, 680, 681, 681, 681, 682, 682, 682, 682, 682, 682, 682, 682, 683, 682, 682, 682, 682,
		682, 682, 682, 682, 682, 682, 682, 682, 684, 685, 86, 86, 86, 86, 686, 686, 686, 686, 686, 687,
		687, 687, 687, 687, 687, 687, 86, 688, 688, 688, 688, 688, 688, 688, 688, 688, 688, 688, 688, 688,
		688, 86, 86, 86, 689, 689, 689, 689, 689, 689, 689, 690, 690, 690, 690, 690, 690, 690, 690, 690,
		690, 690, 690, 690, 690, 86, 86, 691, 691, 691, 691, 691, 691, 691, 691, 692, 692, 692, 692, 692,
		692, 692, 692, 692, 692, 692, 86, 86, 86, 86, 86, 693, 693, 693, 693, 693, 693, 693, 693, 694,
		694, 694, 694, 694, 694, 694, 694, 694, 694, 86, 86, 86, 86, 86, 86, 86, 695, 695, 695, 695, 86,
		86, 86, 86, 696, 696, 696, 696, 696, 696, 696, 697, 697, 697, 697, 697, 697, 697, 697, 697, 86,
		86, 86, 86, 86, 86, 86, 698, 698, 698, 698, 698, 698, 698, 698, 698, 698, 698, 86, 86, 86, 86, 86,
		699, 699, 699, 699, 699, 699, 699, 699, 699, 699, 699, 86, 86, 86, 86, 86, 86, 86, 700, 700, 700,
		700, 700, 700, 701, 701, 701, 701, 701, 701, 701, 701, 701, 701, 701, 701, 702, 702, 702, 702,
		703, 703, 703, 703, 703, 703, 703, 703, 703, 703, 144, 144, 144, 144, 144, 144, 704, 704, 704,
		704, 704, 704, 704, 704, 704, 704, 705, 705, 705, 705, 706, 705, 707, 707, 707, 707, 707, 707,
		707, 707, 707, 707, 707, 707, 707, 707, 86, 86, 86, 708, 708, 708, 708, 708, 709, 706, 710, 710,
		710, 710, 710, 710, 710, 710, 710, 710, 710, 710, 710, 710, 86, 86, 86, 86, 86, 86, 86, 86, 711,
		711, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 712, 86, 713, 713, 713,
		713, 713, 713, 713, 713, 713, 713, 86, 714, 714, 715, 86, 86, 713, 713, 86, 86, 86, 86, 86, 86,
		144, 144, 125, 125, 125, 140, 125, 125, 144, 167, 167, 119, 136, 136, 119, 119, 716, 118, 118,
		118, 118, 118, 118, 118, 118, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125, 125,
		125, 144, 136, 136, 136, 119, 136, 119, 136, 119, 119, 119, 136, 136, 717, 136, 136, 136, 718,
		718, 718, 718, 718, 718, 718, 718, 718, 718, 718, 718, 718, 719, 719, 719, 719, 719, 719, 719,
		719, 719, 719, 718, 720, 720, 720, 720, 720, 720, 720, 720, 720, 720, 720, 720, 720, 720, 721,
		721, 722, 722, 722, 721, 722, 721, 721, 721, 721, 723, 723, 723, 723, 724, 724, 724, 724, 724,
		144, 144, 144, 144, 144, 144, 725, 725, 725, 725, 725, 725, 725, 725, 725, 725, 726, 727, 726,
		727, 728, 728, 728, 728, 86, 86, 86, 86, 86, 86, 729, 729, 729, 729, 729, 729, 729, 729, 729, 729,
		729, 729, 729, 730, 730, 730, 730, 730, 730, 730, 86, 86, 86, 86, 731, 731, 731, 731, 731, 731,
		731, 731, 731, 731, 731, 731, 731, 731, 731, 86, 732, 733, 732, 734, 734, 734, 734, 734, 734, 734,
		734, 734, 734, 734, 734, 734, 733, 733, 733, 733, 733, 733, 733, 733, 733, 733, 733, 733, 733,
		733, 735, 736, 736, 736, 736, 736, 736, 736, 0, 0, 0, 0, 737, 737, 737, 737, 737, 737, 737, 737,
		737, 737, 737, 737, 737, 737, 737, 737, 737, 737, 737, 737, 738, 738, 738, 738, 738, 738, 738,
		738, 738, 738, 735, 734, 734, 733, 733, 734, 0, 0, 0, 0, 0, 0, 0, 0, 0, 735, 739, 739, 740, 741,
		741, 741, 741, 741, 741, 741, 741, 741, 741, 741, 741, 741, 740, 740, 740, 739, 739, 739, 739,
		740, 740, 742, 743, 744, 744, 745, 744, 744, 744, 744, 739, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 745, 0,
		0, 746, 746, 746, 746, 746, 746, 746, 746, 746, 0, 0, 0, 0, 0, 0, 0, 747, 747, 747, 747, 747, 747,
		747, 747, 747, 747, 0, 0, 0, 0, 0, 0, 748, 748, 748, 749, 749, 749, 749, 749, 749, 749, 749, 749,
		749, 749, 749, 749, 749, 749, 749, 749, 749, 749, 749, 750, 750, 750, 750, 750, 751, 750, 750,
		750, 750, 750, 750, 752, 752, 0, 753, 753, 753, 753, 753, 753, 753, 753, 753, 753, 754, 754, 754,
		754, 749, 751, 751, 749, 755, 755, 755, 755, 755, 755, 755, 755, 755, 755, 755, 756, 757, 757,
		755, 0, 758, 758, 759, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760, 760,
		760, 759, 759, 759, 758, 758, 758, 758, 758, 758, 758, 758, 758, 759, 761, 760, 760, 760, 760,
		762, 762, 762, 762, 758, 763, 758, 758, 762, 759, 758, 764, 764, 764, 764, 764, 764, 764, 764,
		764, 764, 760, 762, 760, 762, 762, 762, 0, 765, 765, 765, 765, 765, 765, 765, 765, 765, 765, 765,
		765, 765, 765, 765, 765, 765, 765, 765, 765, 0, 0, 0, 766, 766, 766, 766, 766, 766, 766, 766, 766,
		766, 0, 766, 766, 766, 766, 766, 766, 766, 766, 766, 767, 767, 767, 768, 768, 768, 767, 767, 768,
		769, 770, 768, 771, 771, 771, 771, 771, 771, 768, 766, 766, 768, 0, 0, 0, 0, 0, 0, 772, 772, 772,
		772, 772, 772, 772, 0, 772, 0, 772, 772, 772, 772, 0, 772, 772, 772, 772, 772, 772, 772, 772, 772,
		772, 772, 772, 772, 772, 772, 0, 772, 772, 773, 0, 0, 0, 0, 0, 0, 774, 774, 774, 774, 774, 774,
		774, 774, 774, 774, 774, 774, 774, 774, 774, 775, 776, 776, 776, 775, 775, 775, 775, 775, 775,
		777, 778, 0, 0, 0, 0, 0, 779, 779, 779, 779, 779, 779, 779, 779, 779, 779, 0, 0, 0, 0, 0, 0, 780,
		780, 781, 781, 0, 782, 782, 782, 782, 782, 782, 782, 782, 0, 0, 782, 782, 0, 0, 782, 782, 782,
		782, 782, 782, 782, 782, 782, 782, 782, 782, 782, 782, 0, 782, 782, 782, 782, 782, 782, 782, 0,
		782, 782, 0, 782, 782, 782, 782, 782, 0, 783, 784, 782, 781, 781, 780, 781, 781, 781, 781, 0, 0,
		781, 781, 0, 0, 781, 781, 785, 0, 0, 782, 0, 0, 0, 0, 0, 0, 781, 0, 0, 0, 0, 0, 782, 782, 782,
		782, 782, 781, 781, 0, 0, 786, 786, 786, 786, 786, 786, 786, 0, 0, 0, 787, 787, 787, 787, 787,
		787, 787, 787, 787, 787, 0, 787, 0, 0, 787, 0, 787, 787, 787, 787, 787, 787, 0, 787, 788, 788,
		788, 789, 789, 789, 789, 789, 789, 0, 788, 0, 0, 788, 0, 788, 788, 788, 788, 0, 788, 788, 790,
		791, 790, 787, 789, 787, 792, 792, 0, 792, 792, 0, 0, 0, 0, 0, 0, 0, 0, 789, 789, 0, 0, 0, 0, 0,
		793, 793, 793, 793, 793, 793, 793, 793, 793, 793, 793, 793, 793, 794, 794, 794, 795, 795, 795,
		795, 795, 795, 795, 795, 794, 794, 796, 795, 795, 794, 797, 793, 793, 793, 793, 798, 798, 798,
		798, 798, 799, 799, 799, 799, 799, 799, 799, 799, 799, 799, 798, 798, 0, 798, 800, 793, 793, 793,
		0, 0, 0, 0, 0, 0, 801, 801, 801, 801, 801, 801, 801, 801, 802, 802, 802, 803, 803, 803, 803, 803,
		803, 802, 803, 802, 802, 802, 802, 803, 803, 802, 804, 805, 801, 801, 806, 801, 807, 807, 807,
		807, 807, 807, 807, 807, 807, 807, 0, 0, 0, 0, 0, 0, 808, 808, 808, 808, 808, 808, 808, 808, 808,
		808, 808, 808, 808, 808, 808, 809, 809, 809, 810, 810, 810, 810, 0, 0, 809, 809, 809, 809, 810,
		810, 809, 811, 812, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813, 813,
		808, 808, 808, 808, 810, 810, 0, 0, 814, 814, 814, 814, 814, 814, 814, 814, 815, 815, 815, 816,
		816, 816, 816, 816, 816, 816, 816, 815, 815, 816, 815, 817, 816, 818, 818, 818, 814, 0, 0, 0, 819,
		819, 819, 819, 819, 819, 819, 819, 819, 819, 0, 0, 0, 0, 0, 0, 339, 339, 339, 339, 339, 339, 339,
		339, 339, 339, 339, 339, 339, 0, 0, 0, 820, 820, 820, 820, 820, 820, 820, 820, 820, 820, 820, 821,
		822, 821, 822, 822, 821, 821, 821, 821, 821, 821, 823, 824, 820, 825, 0, 0, 0, 0, 0, 0, 826, 826,
		826, 826, 826, 826, 826, 826, 826, 826, 0, 0, 0, 0, 0, 0, 292, 292, 292, 292, 0, 0, 0, 0, 827,
		827, 827, 827, 827, 827, 827, 827, 827, 827, 827, 0, 0, 828, 829, 828, 829, 829, 828, 828, 828,
		828, 829, 828, 828, 828, 828, 830, 0, 0, 0, 0, 831, 831, 831, 831, 831, 831, 831, 831, 831, 831,
		832, 832, 833, 833, 833, 834, 827, 827, 827, 827, 827, 827, 827, 0, 835, 835, 835, 835, 835, 835,
		835, 835, 835, 835, 835, 835, 836, 836, 836, 837, 837, 837, 837, 837, 837, 837, 837, 837, 836,
		838, 839, 840, 0, 0, 0, 0, 841, 841, 841, 841, 841, 841, 841, 841, 842, 842, 842, 842, 842, 842,
		842, 842, 843, 843, 843, 843, 843, 843, 843, 843, 843, 843, 844, 844, 844, 844, 844, 844, 844,
		844, 844, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 845, 846, 846, 846, 846, 846, 846, 846, 0, 0, 846,
		0, 0, 846, 846, 846, 846, 846, 846, 846, 846, 0, 846, 846, 0, 846, 846, 846, 846, 846, 846, 846,
		846, 847, 847, 847, 847, 847, 847, 0, 847, 847, 0, 0, 848, 848, 849, 850, 846, 847, 846, 847, 851,
		852, 852, 852, 0, 853, 853, 853, 853, 853, 853, 853, 853, 853, 853, 0, 0, 0, 0, 0, 0, 854, 854,
		854, 854, 854, 854, 854, 854, 0, 0, 854, 854, 854, 854, 854, 854, 854, 855, 855, 855, 856, 856,
		856, 856, 0, 0, 856, 856, 855, 855, 855, 855, 857, 854, 858, 854, 855, 0, 0, 0, 859, 860, 860,
		860, 860, 860, 860, 861, 861, 860, 860, 859, 859, 859, 859, 859, 859, 859, 859, 859, 859, 859,
		859, 859, 859, 859, 859, 860, 862, 860, 860, 860, 860, 863, 859, 860, 860, 860, 860, 864, 864,
		864, 864, 864, 864, 864, 864, 862, 865, 866, 866, 866, 866, 866, 866, 867, 867, 866, 866, 866,
		865, 865, 865, 865, 865, 865, 865, 865, 865, 865, 865, 865, 865, 865, 866, 866, 866, 866, 866,
		866, 866, 866, 866, 866, 866, 866, 866, 867, 866, 868, 869, 869, 869, 865, 869, 869, 869, 869,
		869, 0, 0, 0, 0, 0, 870, 870, 870, 870, 870, 870, 870, 870, 870, 0, 0, 0, 0, 0, 0, 0, 178, 178,
		178, 178, 178, 178, 178, 178, 178, 178, 173, 0, 0, 0, 0, 0, 758, 759, 758, 758, 758, 759, 758,
		759, 871, 871, 871, 871, 871, 871, 871, 871, 871, 872, 0, 0, 0, 0, 0, 0, 873, 873, 873, 873, 873,
		873, 873, 873, 873, 873, 0, 0, 0, 0, 0, 0, 874, 874, 874, 874, 874, 874, 874, 874, 874, 0, 874,
		874, 874, 874, 874, 874, 874, 874, 874, 874, 874, 874, 874, 875, 876, 876, 876, 876, 876, 876,
		876, 0, 876, 876, 876, 876, 876, 876, 875, 877, 874, 878, 878, 878, 878, 878, 0, 0, 879, 879, 879,
		879, 879, 879, 879, 879, 879, 879, 880, 880, 880, 880, 880, 880, 880, 880, 880, 880, 880, 880,
		880, 880, 880, 880, 880, 880, 880, 0, 0, 0, 881, 881, 882, 882, 882, 882, 882, 882, 882, 882, 882,
		882, 882, 882, 882, 882, 0, 0, 883, 883, 883, 883, 883, 883, 883, 883, 883, 883, 883, 883, 883,
		883, 0, 884, 883, 883, 883, 883, 883, 883, 883, 884, 883, 883, 884, 883, 883, 0, 885, 885, 885,
		885, 885, 885, 885, 0, 885, 885, 0, 885, 885, 885, 885, 885, 885, 885, 885, 885, 885, 885, 885,
		885, 885, 886, 886, 886, 886, 886, 886, 0, 0, 0, 886, 0, 886, 886, 0, 886, 886, 886, 887, 886,
		888, 888, 885, 886, 889, 889, 889, 889, 889, 889, 889, 889, 889, 889, 0, 0, 0, 0, 0, 0, 890, 890,
		890, 890, 890, 890, 0, 890, 890, 0, 890, 890, 890, 890, 890, 890, 890, 890, 890, 890, 890, 890,
		890, 890, 890, 890, 891, 891, 891, 891, 891, 0, 892, 892, 0, 891, 891, 892, 891, 893, 890, 0, 0,
		0, 0, 0, 0, 0, 894, 894, 894, 894, 894, 894, 894, 894, 894, 894, 0, 0, 0, 0, 0, 0, 895, 895, 895,
		895, 895, 895, 895, 895, 895, 896, 895, 895, 0, 0, 0, 0, 897, 897, 897, 897, 897, 897, 897, 897,
		897, 897, 0, 0, 0, 0, 0, 0, 181, 180, 0, 0, 0, 0, 0, 0, 898, 898, 898, 898, 898, 898, 898, 898,
		898, 898, 898, 899, 899, 900, 900, 901, 901, 0, 0, 0, 0, 0, 0, 0, 902, 902, 903, 904, 903, 903,
		903, 903, 903, 903, 903, 903, 903, 903, 903, 903, 903, 0, 903, 903, 903, 903, 903, 903, 903, 903,
		903, 903, 904, 904, 902, 902, 902, 902, 902, 0, 0, 0, 904, 904, 902, 905, 906, 907, 907, 907, 907,
		907, 907, 907, 907, 907, 907, 907, 907, 907, 908, 908, 908, 908, 908, 908, 908, 908, 908, 908,
		902, 0, 0, 0, 0, 0, 515, 0, 0, 0, 0, 0, 0, 0, 219, 219, 219, 219, 219, 219, 219, 219, 219, 219,
		219, 219, 219, 220, 220, 220, 220, 220, 220, 220, 220, 221, 221, 221, 221, 220, 220, 220, 220,
		220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 220, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
		0, 0, 909, 910, 910, 910, 910, 910, 910, 910, 910, 910, 910, 0, 0, 0, 0, 0, 0, 911, 911, 911, 911,
		911, 911, 911, 911, 912, 912, 912, 912, 912, 911, 911, 911, 910, 910, 910, 910, 0, 0, 0, 0, 913,
		913, 913, 913, 913, 913, 913, 913, 913, 913, 913, 913, 911, 911, 911, 911, 911, 911, 911, 911,
		911, 911, 911, 0, 914, 914, 914, 914, 914, 914, 914, 914, 914, 915, 915, 0, 0, 0, 0, 0, 916, 916,
		916, 916, 916, 916, 916, 916, 917, 917, 917, 917, 917, 917, 917, 917, 918, 916, 916, 916, 916,
		916, 916, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 918, 0, 0, 916,
		916, 916, 0, 0, 0, 0, 0, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919, 919,
		919, 0, 920, 920, 920, 920, 920, 920, 920, 920, 920, 920, 920, 920, 920, 920, 921, 921, 921, 921,
		921, 921, 921, 921, 921, 921, 921, 921, 922, 922, 922, 921, 921, 923, 924, 924, 924, 924, 924,
		924, 924, 924, 924, 924, 0, 0, 0, 0, 0, 0, 525, 0, 0, 0, 0, 0, 0, 0, 925, 925, 925, 925, 925, 925,
		925, 925, 925, 925, 925, 925, 925, 925, 925, 0, 926, 926, 926, 926, 926, 926, 926, 926, 926, 926,
		0, 0, 0, 0, 927, 927, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928, 928,
		0, 929, 929, 929, 929, 929, 929, 929, 929, 929, 929, 0, 0, 0, 0, 0, 0, 930, 930, 930, 930, 930,
		930, 930, 930, 930, 930, 930, 930, 930, 930, 0, 0, 931, 931, 931, 931, 931, 932, 0, 0, 933, 933,
		933, 933, 933, 933, 933, 933, 934, 934, 934, 934, 934, 934, 934, 935, 935, 935, 935, 935, 936,
		936, 936, 936, 937, 937, 937, 937, 935, 936, 0, 0, 938, 938, 938, 938, 938, 938, 938, 938, 938,
		938, 0, 939, 939, 939, 939, 939, 939, 939, 0, 933, 933, 933, 933, 933, 0, 0, 0, 0, 0, 933, 933,
		933, 940, 940, 940, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941, 941,
		941, 940, 940, 942, 942, 942, 943, 943, 943, 943, 943, 943, 943, 943, 943, 943, 0, 0, 0, 0, 0, 0,
		944, 944, 944, 944, 944, 944, 944, 944, 945, 945, 945, 945, 945, 945, 945, 945, 946, 946, 946,
		946, 946, 946, 946, 946, 946, 946, 946, 946, 946, 946, 946, 947, 947, 947, 947, 0, 0, 0, 0, 0,
		948, 948, 948, 948, 948, 948, 948, 948, 948, 0, 0, 949, 949, 949, 949, 949, 949, 949, 949, 949,
		949, 949, 949, 949, 949, 949, 949, 949, 0, 0, 0, 0, 950, 950, 950, 950, 950, 950, 950, 950, 950,
		950, 950, 0, 0, 0, 0, 951, 950, 952, 952, 952, 952, 952, 952, 952, 952, 952, 952, 952, 952, 952,
		952, 952, 0, 0, 0, 0, 0, 0, 0, 951, 951, 951, 951, 953, 953, 953, 953, 953, 953, 953, 953, 953,
		953, 953, 953, 953, 954, 955, 956, 485, 957, 0, 0, 0, 958, 958, 485, 485, 487, 487, 487, 0, 959,
		959, 959, 959, 959, 959, 959, 959, 960, 960, 960, 960, 960, 960, 960, 960, 960, 960, 960, 0, 0, 0,
		0, 0, 0, 0, 0, 0, 0, 0, 0, 960, 959, 0, 0, 0, 0, 0, 0, 0, 959, 959, 959, 0, 0, 0, 0, 0, 961, 961,
		961, 961, 961, 961, 961, 961, 961, 961, 0, 0, 0, 0, 0, 0, 961, 961, 961, 0, 0, 0, 0, 0, 502, 502,
		502, 502, 0, 502, 502, 502, 502, 502, 502, 502, 0, 502, 502, 0, 501, 497, 497, 497, 497, 497, 497,
		497, 501, 501, 501, 497, 501, 501, 501, 501, 501, 0, 0, 0, 0, 0, 0, 0, 0, 0, 497, 0, 0, 0, 0, 0,
		497, 497, 497, 0, 0, 501, 0, 0, 0, 0, 0, 0, 501, 501, 501, 501, 962, 962, 962, 962, 962, 962, 962,
		962, 962, 962, 962, 962, 0, 0, 0, 0, 963, 963, 963, 963, 963, 963, 963, 963, 963, 963, 963, 0, 0,
		0, 0, 0, 963, 963, 963, 963, 963, 0, 0, 0, 963, 0, 0, 0, 0, 0, 0, 0, 963, 963, 0, 0, 964, 965,
		966, 967, 425, 425, 425, 425, 0, 0, 0, 0, 968, 968, 968, 968, 968, 968, 968, 968, 968, 968, 26,
		26, 26, 0, 0, 0, 26, 26, 26, 26, 0, 0, 0, 0, 0, 0, 26, 26, 26, 26, 26, 26, 26, 0, 26, 26, 26, 0,
		0, 0, 0, 0, 0, 0, 0, 459, 459, 459, 448, 448, 448, 448, 448, 448, 0, 0, 969, 969, 969, 969, 969,
		969, 969, 969, 969, 969, 969, 969, 969, 969, 0, 0, 969, 969, 969, 969, 969, 969, 969, 0, 286, 286,
		286, 286, 0, 0, 0, 0, 286, 286, 286, 286, 286, 286, 0, 0, 286, 286, 286, 286, 286, 286, 286, 135,
		135, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 286, 970, 970, 413, 413, 413, 286,
		286, 286, 971, 970, 970, 970, 970, 970, 425, 425, 425, 425, 425, 425, 425, 425, 135, 135, 135,
		135, 135, 135, 135, 135, 286, 286, 77, 77, 77, 77, 77, 135, 135, 286, 286, 286, 286, 286, 286, 77,
		77, 77, 77, 286, 286, 286, 26, 26, 286, 286, 286, 286, 286, 620, 620, 972, 972, 972, 620, 0, 0,
		970, 970, 970, 286, 286, 286, 286, 286, 286, 286, 286, 413, 413, 286, 286, 970, 970, 970, 0, 0, 0,
		0, 0, 0, 535, 535, 535, 535, 0, 0, 0, 0, 465, 465, 465, 465, 465, 465, 465, 0, 505, 505, 505, 505,
		505, 505, 505, 535, 535, 0, 0, 0, 0, 0, 0, 0, 456, 456, 456, 456, 456, 456, 456, 456, 456, 456,
		34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 456, 456, 456, 456, 456,
		456, 456, 456, 456, 456, 34, 34, 34, 34, 34, 34, 34, 0, 34, 34, 34, 34, 34, 34, 456, 0, 456, 456,
		0, 0, 456, 0, 0, 456, 456, 0, 0, 456, 456, 456, 456, 0, 456, 456, 34, 34, 0, 34, 0, 34, 34, 34,
		34, 34, 34, 34, 0, 34, 34, 34, 34, 34, 34, 34, 456, 456, 0, 456, 456, 456, 456, 0, 0, 456, 456,
		456, 456, 456, 456, 456, 456, 0, 456, 456, 456, 456, 456, 456, 456, 0, 34, 34, 456, 456, 0, 456,
		456, 456, 456, 0, 456, 456, 456, 456, 456, 0, 456, 0, 0, 0, 456, 456, 456, 456, 456, 456, 456, 0,
		34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 34, 0, 456, 448, 34, 34, 34, 34, 34, 34, 34, 34,
		34, 459, 34, 34, 34, 34, 34, 34, 456, 456, 456, 456, 456, 456, 456, 456, 456, 448, 34, 34, 34, 34,
		34, 34, 34, 34, 34, 459, 34, 34, 456, 456, 456, 456, 456, 448, 34, 34, 34, 34, 34, 34, 34, 34, 34,
		459, 34, 34, 34, 34, 34, 34, 456, 456, 456, 456, 456, 456, 456, 456, 456, 448, 34, 459, 34, 34,
		34, 34, 34, 34, 34, 34, 456, 34, 0, 0, 968, 968, 973, 973, 973, 973, 973, 973, 973, 973, 974, 974,
		974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 974, 973, 973, 973, 973, 974, 974,
		974, 974, 974, 974, 974, 974, 974, 974, 973, 973, 973, 973, 973, 973, 973, 973, 974, 973, 973,
		973, 973, 973, 973, 974, 973, 973, 975, 975, 975, 975, 975, 0, 0, 0, 0, 0, 0, 0, 974, 974, 974,
		974, 974, 0, 974, 974, 974, 974, 974, 974, 974, 448, 448, 448, 459, 459, 459, 459, 459, 459, 448,
		448, 448, 448, 448, 448, 459, 459, 448, 448, 459, 448, 0, 0, 0, 41, 41, 42, 41, 41, 41, 41, 41,
		37, 41, 41, 41, 41, 41, 41, 41, 41, 37, 41, 41, 41, 41, 41, 41, 42, 42, 0, 0, 0, 0, 0, 0, 0, 0, 0,
		0, 0, 44, 44, 44, 44, 44, 44, 64, 64, 44, 44, 44, 976, 976, 976, 976, 976, 976, 976, 0, 976, 976,
		976, 976, 976, 976, 976, 976, 976, 0, 0, 976, 976, 976, 976, 976, 976, 976, 0, 976, 976, 0, 976,
		976, 976, 976, 976, 0, 0, 0, 0, 0, 416, 416, 416, 416, 416, 416, 416, 416, 416, 416, 416, 416,
		416, 416, 0, 0, 0, 0, 0, 0, 0, 0, 0, 76, 977, 977, 977, 977, 977, 977, 977, 977, 977, 977, 977,
		977, 977, 0, 0, 0, 978, 978, 978, 978, 978, 978, 978, 979, 979, 979, 979, 979, 979, 979, 0, 0,
		980, 980, 980, 980, 980, 980, 980, 980, 980, 980, 0, 0, 0, 0, 977, 981, 982, 982, 982, 982, 982,
		982, 982, 982, 982, 982, 982, 982, 982, 982, 983, 0, 984, 984, 984, 984, 984, 984, 984, 984, 984,
		984, 984, 984, 985, 985, 985, 985, 986, 986, 986, 986, 986, 986, 986, 986, 986, 986, 0, 0, 0, 0,
		0, 987, 988, 988, 988, 988, 988, 988, 988, 988, 988, 988, 988, 989, 990, 990, 991, 992, 993, 993,
		993, 993, 993, 993, 993, 993, 993, 993, 0, 0, 0, 0, 0, 0, 994, 994, 994, 994, 994, 994, 994, 994,
		994, 994, 994, 994, 994, 994, 995, 996, 994, 997, 997, 997, 997, 997, 997, 997, 997, 997, 997, 0,
		0, 0, 0, 998, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 999, 0, 999,
		999, 999, 1000, 999, 999, 1000, 999, 999, 999, 999, 999, 999, 999, 1000, 1000, 999, 999, 999, 999,
		999, 1000, 0, 0, 0, 0, 0, 0, 0, 0, 999, 1001, 301, 301, 301, 301, 0, 301, 301, 0, 1002, 1002,
		1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 1002, 86, 86, 1003, 1003, 1003, 1003,
		1003, 1003, 1003, 1003, 1003, 1004, 1004, 1004, 1004, 1004, 1004, 1004, 86, 1005, 1005, 1005,
		1005, 1005, 1005, 1005, 1005, 1005, 1005, 1006, 1006, 1006, 1006, 1006, 1006, 1006, 1006, 1006,
		1006, 1006, 1006, 1006, 1006, 1006, 1006, 1006, 1006, 1007, 1007, 1007, 1007, 1007, 1007, 1008,
		1009, 86, 86, 86, 86, 1010, 1010, 1010, 1010, 1010, 1010, 1010, 1010, 1010, 1010, 86, 86, 86, 86,
		1011, 1011, 144, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 1012,
		1012, 1012, 1012, 1012, 1012, 1012, 1013, 1012, 1012, 1012, 1014, 1012, 1012, 1012, 1012, 144,
		144, 144, 1012, 1012, 1012, 1012, 1012, 1012, 1013, 1012, 1012, 1012, 1012, 1012, 1012, 1012, 144,
		144, 125, 125, 125, 125, 144, 125, 125, 125, 144, 125, 125, 144, 125, 144, 144, 125, 144, 125,
		125, 125, 125, 125, 125, 125, 125, 125, 125, 144, 125, 125, 125, 125, 144, 125, 144, 125, 144,
		144, 144, 144, 144, 144, 125, 144, 144, 144, 144, 125, 144, 125, 144, 125, 144, 125, 125, 125,
		144, 125, 144, 125, 144, 125, 144, 125, 144, 125, 125, 125, 125, 144, 125, 144, 125, 125, 144,
		125, 125, 125, 125, 125, 125, 125, 125, 125, 144, 144, 144, 144, 144, 125, 125, 125, 144, 125,
		125, 125, 112, 112, 144, 144, 144, 144, 144, 144, 26, 26, 26, 26, 465, 26, 26, 26, 26, 26, 26, 26,
		26, 26, 26, 0, 0, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 0, 0, 33, 33, 33, 460, 460,
		26, 26, 26, 468, 468, 468, 468, 468, 468, 286, 26, 468, 468, 26, 26, 26, 26, 26, 26, 468, 468,
		468, 468, 468, 468, 504, 468, 468, 504, 504, 504, 504, 504, 504, 504, 504, 504, 504, 468, 468,
		468, 468, 468, 468, 468, 468, 468, 468, 26, 504, 0, 0, 0, 0, 0, 0, 0, 286, 286, 1015, 504, 504, 0,
		0, 0, 0, 0, 504, 504, 504, 504, 0, 0, 0, 0, 504, 0, 0, 0, 0, 0, 0, 0, 504, 504, 0, 0, 0, 0, 0, 0,
		26, 26, 26, 26, 26, 465, 465, 465, 465, 465, 465, 465, 465, 465, 26, 465, 465, 465, 465, 465, 465,
		26, 465, 465, 465, 465, 465, 26, 26, 26, 26, 465, 465, 26, 26, 26, 465, 26, 26, 26, 465, 465, 465,
		499, 499, 499, 499, 499, 465, 465, 465, 465, 465, 465, 465, 26, 465, 26, 465, 465, 465, 465, 465,
		465, 465, 465, 465, 465, 465, 26, 26, 465, 465, 465, 465, 465, 465, 465, 26, 26, 26, 26, 26, 465,
		465, 465, 465, 26, 26, 26, 465, 26, 26, 26, 26, 26, 26, 26, 26, 26, 26, 465, 465, 26, 26, 26, 26,
		465, 465, 465, 465, 465, 465, 465, 465, 26, 26, 465, 465, 465, 465, 465, 0, 0, 465, 465, 465, 465,
		26, 26, 26, 465, 465, 0, 0, 0, 26, 26, 26, 26, 465, 465, 465, 465, 465, 465, 465, 465, 465, 0, 0,
		0, 26, 26, 504, 26, 0, 0, 0, 0, 465, 465, 465, 465, 0, 0, 0, 0, 448, 0, 0, 0, 0, 0, 0, 0, 465,
		465, 465, 26, 465, 465, 465, 465, 465, 0, 0, 0, 465, 465, 465, 465, 465, 465, 465, 465, 465, 465,
		0, 465, 465, 465, 465, 465, 0, 0, 0, 465, 465, 465, 465, 0, 0, 0, 0, 0, 26, 26, 26, 0, 26, 26, 26,
		26, 968, 968, 26, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 449, 449, 511, 511, 511, 511, 511, 511, 511,
		582, 511, 582, 582, 582, 582, 582, 582, 582, 582, 582, 582, 582, 582, 582, 449, 449, 511, 511,
		511, 582, 582, 582, 582, 582, 1016, 1016, 1016, 1016, 1016, 1016, 1016, 1016, 449, 425, 449, 449,
		449, 449, 449, 449, 425, 425, 425, 425, 425, 425, 425, 425, 581, 581, 581, 581, 581, 581, 449,
		449,
	];
}
