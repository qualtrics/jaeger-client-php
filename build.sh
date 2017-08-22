#!/bin/sh

LOCAL_DIR=$(cd "$(dirname "$BASH_SOURCE")" && pwd)

# create an artifacts directory
ARTIFACT_DIR=$(mktemp -d --tmpdir=${LOCAL_DIR} 2> /dev/null || mktemp -d ${LOCAL_DIR}/artifact.XXXXXXXXXX)
echo "--Artifact directory: ${ARTIFACT_DIR}"

for FILE in $(find ${LOCAL_DIR}/jaeger-idl/thrift -name *.thrift -depth 1); do

	FILENAME=$(basename ${FILE})

	echo "Compiling ${FILENAME}"

	# compile the .thrift files we'll need
	docker run --rm \
		-v ${LOCAL_DIR}/jaeger-idl/thrift:/data \
		-v ${LOCAL_DIR}/Thrift:/generated/Shared/Libraries/Jaeger/Thrift \
		thrift:0.10 thrift -out /generated --gen php:psr4,nsglobal="Shared\Libraries\Jaeger\Thrift" /data/${FILENAME}

done

echo "--Removing existing generated files"
rm -rf ${ARTIFACT_DIR}