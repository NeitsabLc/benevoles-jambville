ARG POSTGRES_VERSION=18-alpine
ARG POSTGRES_IMAGE_DIGEST=sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15

FROM postgres:${POSTGRES_VERSION}@${POSTGRES_IMAGE_DIGEST} AS postgres-runtime

USER root
RUN apk upgrade --no-cache c-ares \
    && rm -f /usr/local/bin/gosu

USER postgres
