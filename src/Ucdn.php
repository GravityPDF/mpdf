<?php

namespace Mpdf;

/**
 * The old name of Mpdf\Unicode\Ucdn, kept so configuration written against it, such as
 * 'baseScript' => \Mpdf\Ucdn::SCRIPT_LATIN, keeps working
 *
 * @deprecated Use Mpdf\Unicode\Ucdn
 */
class Ucdn extends Unicode\Ucdn
{

}
