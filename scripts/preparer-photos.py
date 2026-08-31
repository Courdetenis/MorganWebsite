#!/usr/bin/env python3
"""
Prépare le dossier de photos à déposer sur l'hébergement.

Convertit les JPEG de `pictures/` en WebP 2000 px, comme le ferait `media.php` —
mais ici, sur le poste, où le temps de calcul et la mémoire ne sont pas comptés.
Sur un hébergement mutualisé, PHP est coupé au bout de trente à cent vingt
secondes et plafonné à 128 Mo : convertir vingt-trois photos de 4000 px n'y
passerait pas. En les envoyant déjà converties, l'installateur n'a plus qu'à les
copier.

Le numéro en tête de nom est conservé : c'est lui, et lui seul, qui relie un
fichier à son entrée dans `theme/install/photos.php`.

    python3 scripts/preparer-photos.py

Puis déposer le contenu de `dist/photos/` par FTP dans
`wp-content/uploads/morgane-photos/`.
"""

import pathlib
import sys

try:
    from PIL import Image
except ImportError:
    sys.exit("Pillow manquant :  pip install --user Pillow")

MAX_COTE = 2000   # même valeur que media.php
QUALITE = 82      # idem

racine = pathlib.Path(__file__).resolve().parent.parent
source = racine / "pictures"
cible = racine / "dist" / "photos"

if not source.is_dir():
    sys.exit(f"Dossier introuvable : {source}")

cible.mkdir(parents=True, exist_ok=True)

fichiers = sorted(
    (f for f in source.iterdir()
     if f.suffix.lower() in {".jpg", ".jpeg", ".png"} and f.name[0].isdigit()),
    key=lambda f: int(f.name.split("-")[0].strip()),
)

if not fichiers:
    sys.exit(f"Aucune photo numérotée dans {source}")

total_avant = total_apres = 0

for f in fichiers:
    destination = cible / (f.stem + ".webp")

    with Image.open(f) as im:
        im = im.convert("RGB")
        if max(im.size) > MAX_COTE:
            ratio = MAX_COTE / max(im.size)
            im = im.resize(
                (round(im.width * ratio), round(im.height * ratio)),
                Image.LANCZOS,
            )
        im.save(destination, "WEBP", quality=QUALITE, method=6)

    avant, apres = f.stat().st_size, destination.stat().st_size
    total_avant += avant
    total_apres += apres
    print(f"  {f.name:32} → {destination.name:32} {avant // 1024:5} Ko → {apres // 1024:4} Ko")

print(
    f"\n{len(fichiers)} photos : {total_avant // 1048576} Mo → {total_apres // 1048576} Mo"
    f"\nÀ déposer par FTP dans wp-content/uploads/morgane-photos/ :\n  {cible}"
)
