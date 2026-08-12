param(
    [string]$File = "shot-full.png",
    [string]$Prefix = "shot-part",
    [int]$SliceHeight = 2400
)
Add-Type -AssemblyName System.Drawing
$dir = Join-Path $env:USERPROFILE "trendinform"
$img = [System.Drawing.Image]::FromFile((Join-Path $dir $File))
Write-Host "Groesse: $($img.Width)x$($img.Height)"
$n = [Math]::Ceiling($img.Height / $SliceHeight)
for ($i = 0; $i -lt $n; $i++) {
    $h = [Math]::Min($SliceHeight, $img.Height - $i * $SliceHeight)
    $bmp = New-Object System.Drawing.Bitmap($img.Width, $h)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $destRect = New-Object System.Drawing.Rectangle(0, 0, $img.Width, $h)
    $srcRect  = New-Object System.Drawing.Rectangle(0, ($i * $SliceHeight), $img.Width, $h)
    $g.DrawImage($img, $destRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
    $bmp.Save((Join-Path $dir "$Prefix$i.png"))
    $g.Dispose(); $bmp.Dispose()
    Write-Host "$Prefix$i.png geschrieben"
}
$img.Dispose()
