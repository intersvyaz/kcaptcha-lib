<?php

/**
 * KCAPTCHA
 * Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
 * www.captcha.ru, www.kruglov.ru
 */

namespace Ishenkoyv\KCaptcha;

class KCaptcha
{
    protected $alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!

    # symbols used to draw CAPTCHA
    //$allowed_symbols = "0123456789"; #digits
    //$allowed_symbols = "23456789abcdegkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
    protected $allowedSymbols = "23456789abcdegikpqsvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)

    # folder with fonts
    protected $fontsdir = '../../../fonts';

    # CAPTCHA string length
    protected $length = 6; # random 5 or 6 or 7

    # CAPTCHA image size (you do not need to change it, this parameters is optimal)
    protected $width = 160;

    protected $height = 80;

    # symbol's vertical fluctuation amplitude
    protected $fluctuationAmplitude = 8;

    #noise
    //$white_noise_density=0; // no white noise
    // 1/6
    protected $whiteNoiseDensity = 0.17;

    //$black_noise_density=0; // no black noise
    // 1/30
    protected $blackNoiseDensity = 0.03;

    # increase safety by prevention of spaces between symbols
    protected $noSpaces = true;

    # show credits
    protected $showCredits = true; # set to false to remove credits line. Credits adds 12 pixels to image height

    protected $credits = 'www.captcha.ru'; # if empty, HTTP_HOST will be shown

    # CAPTCHA image colors (RGB, 0-255)
    //$foreground_color = array(0, 0, 0);
    //$background_color = array(220, 230, 255);
    protected $foregroundColor = 255;

    protected $backgroundColor = 0;

    # JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
    protected $jpegQuality = 90;
    // generates keystring and image
    public function __construct($params = array())
    {
        if (is_array($params)) {
            foreach ($params as $propertyName => $propertyValue) {
                if (property_exists($this, $propertyName)) {
                    $this->$propertyName = $propertyValue;
                }
            }
        }

        // Use random foreground/background colors
        $this->foregroundColor = array(mt_rand(0,80), mt_rand(0,80), mt_rand(0,80));
        $this->backgroundColor = array(mt_rand(220,255), mt_rand(220,255), mt_rand(220,255));

        $fonts = array();
        $fontsdir_absolute = dirname(__FILE__) . '/' . $this->fontsdir;
        if ($handle = opendir($fontsdir_absolute)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match('/\.png$/i', $file)) {
                    $fonts[] = $fontsdir_absolute . '/' . $file;
                }
            }
            closedir($handle);
        }

        $alphabet_length = strlen($this->alphabet);

        do {
            // generating random keystring
            while (true) {
                $this->keystring = '';
                for ($i = 0; $i < $this->length; $i++) {
                    $this->keystring .= $this->allowedSymbols{mt_rand(0, strlen($this->allowedSymbols) - 1)};
                }
                if(!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keystring)) break;
            }

            $font_file = $fonts[mt_rand(0, count($fonts)-1)];
            $font = imagecreatefrompng($font_file);
            imagealphablending($font, true);

            $fontfile_width = imagesx($font);
            $fontfile_height = imagesy($font)-1;

            $font_metrics = array();
            $symbol = 0;
            $reading_symbol = false;

            // loading font
            for ($i = 0; $i < $fontfile_width && $symbol < $alphabet_length; $i++) {
                $transparent = (imagecolorat($font, $i, 0) >> 24) == 127;

                if (!$reading_symbol && !$transparent) {
                    $font_metrics[$this->alphabet{$symbol}] = array('start' => $i);
                    $reading_symbol=true;
                    continue;
                }

                if ($reading_symbol && $transparent) {
                    $font_metrics[$this->alphabet{$symbol}]['end'] = $i;
                    $reading_symbol = false;
                    $symbol++;
                    continue;
                }
            }

            $img = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($img, true);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);

            imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $white);

            // draw text
            $x = 1;
            $odd = mt_rand(0,1);
            if ($odd == 0) $odd = -1;
            for ($i=0; $i<$this->length;$i++) {
                $m=$font_metrics[$this->keystring{$i}];

                $y=(($i%2)*$this->fluctuationAmplitude - $this->fluctuationAmplitude/2)*$odd
                    + mt_rand(-round($this->fluctuationAmplitude/3), round($this->fluctuationAmplitude/3))
                    + ($this->height-$fontfile_height)/2;

                if ($this->noSpaces) {
                    $shift=0;
                    if ($i>0) {
                        $shift=10000;
                        for ($sy=3;$sy<$fontfile_height-10;$sy+=1) {
                            for ($sx=$m['start']-1;$sx<$m['end'];$sx+=1) {
                                $rgb=imagecolorat($font, $sx, $sy);
                                $opacity=$rgb>>24;
                                if ($opacity<127) {
                                    $left=$sx-$m['start']+$x;
                                    $py=$sy+$y;
                                    if($py>$this->height) break;
                                    for ($px=min($left, $this->width-1);$px>$left-200 && $px>=0;$px-=1) {
                                        $color=imagecolorat($img, $px, $py) & 0xff;
                                        if ($color+$opacity<170) { // 170 - threshold
                                            if ($shift>$left-$px) {
                                                $shift=$left-$px;
                                            }
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        if ($shift==10000) {
                            $shift=mt_rand(4,6);
                        }

                    }
                } else {
                    $shift=1;
                }
                imagecopy($img, $font, $x-$shift, $y, $m['start'], 1, $m['end']-$m['start'], $fontfile_height);
                $x+=$m['end']-$m['start']-$shift;
            }
        } while ($x >= $this->width - 10); // while not fit in canvas

        //noise
        $white=imagecolorallocate($font, 255, 255, 255);
        $black=imagecolorallocate($font, 0, 0, 0);
        for ($i=0;$i<(($this->height-30)*$x)*$this->whiteNoiseDensity;$i++) {
            imagesetpixel($img, mt_rand(0, $x-1), mt_rand(10, $this->height-15), $white);
        }
        for ($i=0;$i<(($this->height-30)*$x)*$this->blackNoiseDensity;$i++) {
            imagesetpixel($img, mt_rand(0, $x-1), mt_rand(10, $this->height-15), $black);
        }

        $center=$x/2;

        // credits. To remove, see configuration file
        $img2=imagecreatetruecolor($this->width, $this->height+($this->showCredits?12:0));
        $foreground=imagecolorallocate($img2, $this->foregroundColor[0], $this->foregroundColor[1], $this->foregroundColor[2]);
        $background=imagecolorallocate($img2, $this->backgroundColor[0], $this->backgroundColor[1], $this->backgroundColor[2]);
        imagefilledrectangle($img2, 0, 0, $this->width-1, $this->height-1, $background);
        imagefilledrectangle($img2, 0, $this->height, $this->width-1, $this->height+12, $foreground);
        $this->credits=empty($this->credits)?$_SERVER['HTTP_HOST']:$this->credits;
        imagestring($img2, 2, $this->width/2-imagefontwidth(2)*strlen($this->credits)/2, $this->height-2, $this->credits, $background);

        // periods
        $rand1=mt_rand(750000,1200000)/10000000;
        $rand2=mt_rand(750000,1200000)/10000000;
        $rand3=mt_rand(750000,1200000)/10000000;
        $rand4=mt_rand(750000,1200000)/10000000;
        // phases
        $rand5=mt_rand(0,31415926)/10000000;
        $rand6=mt_rand(0,31415926)/10000000;
        $rand7=mt_rand(0,31415926)/10000000;
        $rand8=mt_rand(0,31415926)/10000000;
        // amplitudes
        $rand9=mt_rand(330,420)/110;
        $rand10=mt_rand(330,450)/100;

        //wave distortion

        for ($x=0;$x<$this->width;$x++) {
            for ($y=0;$y<$this->height;$y++) {
                $sx=$x+(sin($x*$rand1+$rand5)+sin($y*$rand3+$rand6))*$rand9-$this->width/2+$center+1;
                $sy=$y+(sin($x*$rand2+$rand7)+sin($y*$rand4+$rand8))*$rand10;

                if ($sx<0 || $sy<0 || $sx>=$this->width-1 || $sy>=$this->height-1) {
                    continue;
                } else {
                    $color=imagecolorat($img, $sx, $sy) & 0xFF;
                    $color_x=imagecolorat($img, $sx+1, $sy) & 0xFF;
                    $color_y=imagecolorat($img, $sx, $sy+1) & 0xFF;
                    $color_xy=imagecolorat($img, $sx+1, $sy+1) & 0xFF;
                }

                if ($color==255 && $color_x==255 && $color_y==255 && $color_xy==255) {
                    continue;
                } elseif ($color==0 && $color_x==0 && $color_y==0 && $color_xy==0) {
                    $newred=$this->foregroundColor[0];
                    $newgreen=$this->foregroundColor[1];
                    $newblue=$this->foregroundColor[2];
                } else {
                    $frsx=$sx-floor($sx);
                    $frsy=$sy-floor($sy);
                    $frsx1=1-$frsx;
                    $frsy1=1-$frsy;

                    $newcolor=(
                        $color*$frsx1*$frsy1+
                        $color_x*$frsx*$frsy1+
                        $color_y*$frsx1*$frsy+
                        $color_xy*$frsx*$frsy);

                    if($newcolor>255) $newcolor=255;
                    $newcolor=$newcolor/255;
                    $newcolor0=1-$newcolor;

                    $newred=$newcolor0*$this->foregroundColor[0]+$newcolor*$this->backgroundColor[0];
                    $newgreen=$newcolor0*$this->foregroundColor[1]+$newcolor*$this->backgroundColor[1];
                    $newblue=$newcolor0*$this->foregroundColor[2]+$newcolor*$this->backgroundColor[2];
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
            }
        }

        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');
        if (function_exists("imagejpeg")) {
            header("Content-Type: image/jpeg");
            imagejpeg($img2, null, $this->jpegQuality);
        } elseif (function_exists("imagegif")) {
            header("Content-Type: image/gif");
            imagegif($img2);
        } elseif (function_exists("imagepng")) {
            header("Content-Type: image/x-png");
            imagepng($img2);
        }
    }

    // returns keystring
    public function getKeyString()
    {
        return $this->keystring;
    }
}
