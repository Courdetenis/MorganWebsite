<#
.SYNOPSIS
  Fabrique le zip du thème, celui qu'on téléverse dans « Apparence > Thèmes ».

.DESCRIPTION
  Le fichier sort dans `dist/`, nommé d'après la version lue dans
  `theme/style.css` — la seule source de vérité pour le numéro.

  Pourquoi un script pour une chose si simple : `tar -a -cf morgane.zip theme`
  ne fait PAS de zip. bsdtar, livré avec Windows, ignore l'extension et écrit
  une archive tar sous un nom en « .zip ». WordPress la refuse avec « Archive
  incompatible », et le fichier n'a rien qui saute aux yeux — il pèse le poids
  attendu et s'ouvre dans l'explorateur. C'est arrivé, deux fois de suite.

  Le compresseur de .NET, lui, écrit un vrai zip. Le script vérifie la
  signature à la fin plutôt que de faire confiance.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/faire-zip.ps1
#>

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$racine = Split-Path -Parent $PSScriptRoot
$source = Join-Path $racine 'theme'
$sortie = Join-Path $racine 'dist'

# La version, lue dans l'en-tête du thème. WordPress lit la même ligne.
$entete = Get-Content (Join-Path $source 'style.css') -TotalCount 20
$ligne  = @($entete | Where-Object { $_ -match '^Version:\s*(.+)$' })
if (-not $ligne) { throw "Pas de ligne « Version: » dans theme/style.css." }
$version = ($ligne[0] -replace '^Version:\s*', '').Trim()

if (-not (Test-Path $sortie)) { New-Item -ItemType Directory -Path $sortie | Out-Null }
$zip = Join-Path $sortie "morgane-$version.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }

<#
  Les entrées sont nommées à la main, préfixées « morgane/ » : WordPress veut
  un dossier unique à la racine de l'archive, et c'est son nom qui devient
  celui du thème dans wp-content/themes/. Les séparateurs sont forcés en « / »,
  seuls valides dans un zip — .NET les écrirait en antislash sur Windows.
#>
$archive = [System.IO.Compression.ZipFile]::Open($zip, 'Create')
$nombre  = 0
try {
  Get-ChildItem -Path $source -Recurse -File | Sort-Object FullName | ForEach-Object {
    $chemin = 'morgane/' + $_.FullName.Substring($source.Length + 1).Replace('\', '/')
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
      $archive, $_.FullName, $chemin, [System.IO.Compression.CompressionLevel]::Optimal)
    $nombre++
  }
} finally {
  $archive.Dispose()
}

# Un zip commence par « PK^C^D ». Sans ce contrôle, une archive tar déguisée
# passerait inaperçue jusqu'au refus de WordPress.
$tete = [System.IO.File]::ReadAllBytes($zip)[0..3]
if (($tete[0] -ne 0x50) -or ($tete[1] -ne 0x4B) -or ($tete[2] -ne 0x03) -or ($tete[3] -ne 0x04)) {
  Remove-Item $zip -Force
  throw "Le fichier produit n'est pas un zip (signature $($tete -join ' ')). Rien n'a été gardé."
}

"{0}  —  {1} fichiers, {2:N0} Ko" -f $zip, $nombre, ((Get-Item $zip).Length / 1KB)
