param()
Add-Type -AssemblyName System.Drawing

$src = Join-Path $env:USERPROFILE "OneDrive - leadwerk.de\Kunden\Trend in Form GmbH\Leadwerk"
$dst = Join-Path $env:USERPROFILE "trendinform\assets"

$map = @(
    @{ in = "$src\Badezimmer\Kienzler (2).jpg";               out = "hero.jpg";      w = 2000 },
    @{ in = "$src\Badezimmer\Kienzler (3).jpg";               out = "bad-dark.jpg";  w = 1600 },
    @{ in = "$src\Badezimmer\Hofmann_Bad (2).jpg";            out = "bad-1.jpg";     w = 1600 },
    @{ in = "$src\Badezimmer\IMG_4884.jpg";                   out = "bad-2.jpg";     w = 1600 },
    @{ in = "$src\Badezimmer\Hofmann_Bad (1).jpg";            out = "bad-3.jpg";     w = 1600 },
    @{ in = "$src\Badezimmer\Warth1.jpeg";                    out = "bad-4.jpg";     w = 1600 },
    @{ in = "$src\Licht\indirekte_Esszimmerleuchte.jpg";      out = "wohnen-1.jpg";  w = 1600 },
    @{ in = "$src\Licht\IMG_5518.jpg";                        out = "akustik-1.jpg"; w = 1600 },
    @{ in = "$src\Gewerbekunden\Daimler_Mediacube.jpg";       out = "objekt-1.jpg";  w = 1600 },
    @{ in = "$src\Gewerbekunden\AMG-Essen_1.jpg";             out = "objekt-2.jpg";  w = 1600 },
    @{ in = "$src\Gewerbekunden\Daimler_Mediacube_4.jpg";     out = "objekt-3.jpg";  w = 1600 },
    @{ in = "$src\Badezimmer\Badezimmer_Nils\Badezimmer_vorher.jpg";  out = "vergleich-bad-vorher.jpg";    w = 1400 },
    @{ in = "$src\Badezimmer\Badezimmer_Nils\Badezimmer_nachher.jpg"; out = "vergleich-bad-nachher.jpg";   w = 1400 },
    @{ in = "$src\Badezimmer\Kunde 1\IMG_5779.jpg";                   out = "vergleich-marmor-vorher.jpg"; w = 1400 },
    @{ in = "$src\Badezimmer\Kunde 1\IMG_5887.jpg";                   out = "vergleich-marmor-nachher.jpg"; w = 1400 }
)

$jpegCodec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
$encParams = New-Object System.Drawing.Imaging.EncoderParameters(1)
$encParams.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]80)

foreach ($m in $map) {
    if (-not (Test-Path $m.in)) { Write-Host "FEHLT: $($m.in)"; continue }
    $img = [System.Drawing.Image]::FromFile($m.in)

    # EXIF-Orientierung anwenden, damit Fotos nicht gedreht erscheinen
    $orientProp = $img.PropertyIdList | Where-Object { $_ -eq 274 }
    if ($orientProp) {
        $o = $img.GetPropertyItem(274).Value[0]
        switch ($o) {
            3 { $img.RotateFlip([System.Drawing.RotateFlipType]::Rotate180FlipNone) }
            6 { $img.RotateFlip([System.Drawing.RotateFlipType]::Rotate90FlipNone) }
            8 { $img.RotateFlip([System.Drawing.RotateFlipType]::Rotate270FlipNone) }
        }
    }

    $scale = [Math]::Min(1.0, $m.w / $img.Width)
    $nw = [int]($img.Width * $scale); $nh = [int]($img.Height * $scale)
    $bmp = New-Object System.Drawing.Bitmap($nw, $nh)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.DrawImage($img, 0, 0, $nw, $nh)
    $outPath = Join-Path $dst $m.out
    $bmp.Save($outPath, $jpegCodec, $encParams)
    $g.Dispose(); $bmp.Dispose(); $img.Dispose()
    Write-Host ("OK: {0} -> {1} ({2}x{3})" -f (Split-Path $m.in -Leaf), $m.out, $nw, $nh)
}
