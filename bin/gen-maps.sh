#!/bin/sh

set -e -u

# this scripts parent directory
DIR=$(dirname $(dirname $(realpath $0)))

MODES="world server"
STYLES="atys atys_sp"
echo "+ generating '${MODES}' map tiles for '${STYLES}'"
for m in ${MODES}; do
	for s in ${STYLES}; do
		php ${DIR}/bin/bmmaps.php bmmaps:tiles --mapmode ${m} \
			--mapname ${s} --with-map --with-city \
			--mapdir ${DIR}/resources/maps/${s}
	done
done

