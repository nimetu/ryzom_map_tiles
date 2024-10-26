#!/bin/sh

set -e -u

# this scripts partent directory
DIR=$(dirname $(dirname $(realpath $0)))

MODES="world server"
LANGUAGES="en,fr,de,es,ru"

#MODES="server"
#LANGUAGES="en"
for m in ${MODES}; do
	echo "+ generating '${LANGUAGES}' language tiles"
	php ${DIR}/bin/bmmaps.php bmmaps:tiles --mapmode ${m} \
		--lang ${LANGUAGES}
done

