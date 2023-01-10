#!/bin/sh

set -e # Fail fast

LOCAL_DIR=$(cd "$(dirname "$BASH_SOURCE")" && pwd)
BUILDER_IMAGE=jaeger-client-php-tester

if [[ "$(docker images -q $BUILDER_IMAGE 2> /dev/null)" == "" ]]; then
	echo "--Creating builder image"
	docker build --no-cache --file test.Dockerfile --tag $BUILDER_IMAGE .
fi

if [ ! -d "$LOCAL_DIR/vendor" ]; then
	echo "--No vendor directory detected. Running a composer install"
	docker run --rm \
		--volume $LOCAL_DIR:/app \
		$BUILDER_IMAGE \
		install
fi

echo "--Running tests"
docker run --rm \
	--volume $LOCAL_DIR:/app \
	$BUILDER_IMAGE \
	test
