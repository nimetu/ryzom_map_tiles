#!/bin/sh

[ -f bmmaps.php ] && DIR=../ || DIR=.

MODE=world
echo "+ generating '${MODE}' map tiles"
STYLES="atys atys_sp"
for s in ${STYLES}; do
    php ${DIR}/bin/bmmaps.php bmmaps:tiles --mapmode ${MODE} \
        --mapname ${s} --with-map --with-city \
        --mapdir ${DIR}/resources/maps/${s}
done

LANGUAGES='en,fr,de,es,ru'
if [ ! -z $LANGUAGES ]; then
    echo "+ generating '${LANGUAGES}' language tiles"
	php ${DIR}/bin/bmmaps.php bmmaps:tiles --mapmode ${MODE} \
		--lang ${LANGUAGES}
fi

