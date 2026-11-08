<#
.SYNOPSIS
  Fabrique le zip du thème Shopify, celui qu'on importe dans
  « Boutique en ligne › Thèmes › Ajouter un thème › Importer un fichier zip ».

.DESCRIPTION
  Le fichier sort dans `dist/`, nommé d'après `theme_version` de
  `shopify-theme/config/settings_schema.json`.

  Même compresseur .NET que faire-zip.ps1, pour la même raison : `tar -a`
  écrit une archive tar sous un nom en « .zip ». Différence avec WordPress :
  Shopify veut les dossiers du thème (layout/, templates/…) à la racine de
  l'archive, sans dossier englobant.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/faire-zip-shopify.ps1
#>

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$racine = Split-Path -Parent $PSScriptRoot
$source = Join-Path $racine 'shopify-theme'
$sortie = Join-Path $racine 'dist'

$schema  = Get-Content (Join-Path $source 'config/settings_schema.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$version = $schema[0].theme_version
if (-not $version) { throw "Pas de « theme_version » dans shopify-theme/config/settings_schema.json." }

if (-not (Test-Path $sortie)) { New-Item -ItemType Directory -Path $sortie | Out-Null }
$zip = Join-Path $sortie "morgane-shopify-$version.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }

# Seuls les dossiers que Shopify connaît : un fichier à la racine (README)
# ferait refuser l'archive.
$dossiers = 'assets', 'config', 'layout', 'locales', 'sections', 'snippets', 'templates'

$archive = [System.IO.Compression.ZipFile]::Open($zip, 'Create')
$nombre  = 0
try {
  foreach ($d in $dossiers) {
    Get-ChildItem -Path (Join-Path $source $d) -Recurse -File | Sort-Object FullName | ForEach-Object {
      $chemin = $_.FullName.Substring($source.Length + 1).Replace('\', '/')
      [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $archive, $_.FullName, $chemin, [System.IO.Compression.CompressionLevel]::Optimal)
      $nombre++
    }
  }
} finally {
  $archive.Dispose()
}

$tete = [System.IO.File]::ReadAllBytes($zip)[0..3]
if ($tete[0] -ne 0x50 -or $tete[1] -ne 0x4B -or $tete[2] -ne 3 -or $tete[3] -ne 4) {
  throw "Le fichier produit n'est pas un zip : $zip"
}

$taille = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "$zip — $nombre fichiers, $taille Mo"
