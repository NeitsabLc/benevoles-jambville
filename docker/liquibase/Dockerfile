ARG LIQUIBASE_VERSION=5.0.4
ARG LIQUIBASE_IMAGE_DIGEST=sha256:060f96efff4811ccf03957ff3160f21ea04139b89940640f8ad54a065cdc6906
ARG PGJDBC_VERSION=42.7.15
ARG PGJDBC_SHA256=9776fca62dfc84f312d5c1aff9791f6f3cf56d518a8eca4a3a8dca7a95ef8881

FROM liquibase/liquibase:${LIQUIBASE_VERSION}@${LIQUIBASE_IMAGE_DIGEST} AS liquibase-runtime

ARG PGJDBC_VERSION
ARG PGJDBC_SHA256
ARG OS_PACKAGE_REFRESH=2026-11-11

USER root
RUN test -n "$OS_PACKAGE_REFRESH" \
    && apt-get update \
    && apt-get upgrade --yes --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* \
    && curl --fail --silent --show-error --location \
        --output /liquibase/lib/postgresql.jar \
        "https://repo.maven.apache.org/maven2/org/postgresql/postgresql/${PGJDBC_VERSION}/postgresql-${PGJDBC_VERSION}.jar" \
    && echo "${PGJDBC_SHA256}  /liquibase/lib/postgresql.jar" | sha256sum --check --strict \
    && rm -f /liquibase/bin/lpm

COPY --chown=liquibase:liquibase database/changelog /liquibase/changelog

USER liquibase:liquibase
