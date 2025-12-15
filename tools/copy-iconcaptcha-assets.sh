#!/bin/bash

CLIENT_SOURCE="./vendor/fabianwennink/iconcaptcha/assets/client"
ASSETS_SOURCE="./vendor/fabianwennink/iconcaptcha/assets"
TARGET_BASE="./public/assets/iconcaptcha"

DIRS_CLIENT=("js" "css")
DIRS_ASSETS=("icons")
FILES=("placeholder.png")

echo "Copying IconCaptcha assets..."

for DIR in "${DIRS_CLIENT[@]}"; do
    SRC="$CLIENT_SOURCE/$DIR"
    DEST="$TARGET_BASE/$DIR"

    if [ ! -d "$SRC" ]; then
        echo "Source not found: $SRC"
        continue
    fi

    mkdir -p "$DEST"
    cp -r "$SRC"/* "$DEST"/
    echo "Copied $DIR to $DEST"
done

for DIR in "${DIRS_ASSETS[@]}"; do
    SRC="$ASSETS_SOURCE/$DIR"
    DEST="$TARGET_BASE/$DIR"

    if [ ! -d "$SRC" ]; then
        echo "Source not found: $SRC"
        continue
    fi

    mkdir -p "$DEST"
    cp -r "$SRC"/* "$DEST"/
    echo "Copied $DIR to $DEST"
done

for FILE in "${FILES[@]}"; do
    SRC="$ASSETS_SOURCE/$FILE"
    DEST="$TARGET_BASE/$FILE"

    if [ ! -f "$SRC" ]; then
        echo "Source not found: $SRC"
        continue
    fi

    mkdir -p "$(dirname "$DEST")"
    cp "$SRC" "$DEST"
    echo "Copied $SRC to $DEST"
done

echo "Done!"
