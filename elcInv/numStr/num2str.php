<?php 

class numtostr
{
    private static $UNIDADES = [
        '',
        'un ',
        'dos ',
        'tres ',
        'cuatro ',
        'cinco ',
        'seis ',
        'siete ',
        'ocho ',
        'nueve ',
        'diez ',
        'once ',
        'doce ',
        'trece ',
        'catorce ',
        'quince ',
        'dieciseis ',
        'diecisiete ',
        'dieciocho ',
        'diecinueve ',
        'veinte '
    ];

    private static $DECENAS = [
        'venti',
        'treinta ',
        'cuarenta ',
        'cincuenta ',
        'sesenta ',
        'setenta ',
        'ochenta ',
        'noventa ',
        'cien '
    ];

    private static $CENTENAS = [
        'ciento ',
        'doscientos ',
        'trescientos ',
        'cuatrocientos ',
        'quinientos ',
        'seiscientos ',
        'setecientos ',
        'ochocientos ',
        'novecientos '
    ];

    public static function convertir($number, $currency = '', $format = false, $decimals = '')
    {
        $converted = '';

        if (($number < 0) || ($number > 999999999)) {
            return 'No es posible convertir el numero en letras';
        }

        // Round to 2 decimals first: floating point sums (e.g. 337667.996) must
        // carry into the whole part instead of being truncated as-is, and the
        // cents string must always be exactly 2 digits.
        $numberFormatted = number_format((float) $number, 2, '.', '');
        list($base_number, $decNumberStr) = explode('.', $numberFormatted);

        $decNumberStrFill = str_pad($decNumberStr, 9, '0', STR_PAD_LEFT);
        $decCientos = substr($decNumberStrFill, 6);
        $decimales = self::convertGroup($decCientos);

        $numberStr = (string) $base_number;
        $numberStrFill = str_pad($numberStr, 9, '0', STR_PAD_LEFT);
        $millones = substr($numberStrFill, 0, 3);
        $miles = substr($numberStrFill, 3, 3);
        $cientos = substr($numberStrFill, 6);

        if (intval($millones) > 0) {
            if ($millones == '001') {
                $converted .= 'un millon ';
            } else if (intval($millones) > 0) {
                $converted .= sprintf('%smillones ', self::convertGroup($millones));
            }
        }

        if (intval($miles) > 0) {
            if ($miles == '001') {
                $converted .= 'mil ';
            } else if (intval($miles) > 0) {
                $converted .= sprintf('%smil ', self::convertGroup($miles));
            }
        }

        if (intval($cientos) > 0) {
            if ($cientos == '001') {
                $converted .= 'un ';
            } else if (intval($cientos) > 0) {
                $converted .= sprintf('%s ', self::convertGroup($cientos));
            }
        }

        if($format){
            if(empty($decimales)){
                $valor_convertido = ucfirst($converted) . 'Pesos '. $decNumberStr . '/100 '.$currency.'';
            } else {
                $valor_convertido =  ucfirst($converted) .'Pesos '. $decNumberStr . '/100 '.$currency.'';
            }
        }else{
            if(empty($decimales)){
                $valor_convertido = ucfirst($converted) . $currency;
            } else {
                $valor_convertido = ucfirst($converted) . $currency. ' con ' . $decimales . $decimals;
            }
        }

        return $valor_convertido;
    }

    private static function convertGroup($n)
    {
        $output = '';

        if ($n == '100') {
            $output = "cien ";
        } else if ($n[0] !== '0') {
            $output = self::$CENTENAS[$n[0] - 1];
        }

        $k = intval(substr($n,1));

        if ($k <= 20) {
            $output .= self::$UNIDADES[$k];
        } else {
            if(($k > 30) && ($n[2] !== '0')) {
                $output .= sprintf('%sy %s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            } else {
                $output .= sprintf('%s%s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            }
        }

        return $output;
    }
}
 ?>