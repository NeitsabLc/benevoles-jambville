ARG LIQUIBASE_VERSION=5.0.1
ARG LIQUIBASE_IMAGE_DIGEST=sha256:ec5b6ef176df3b39d69a67af728dbe0baa39ccae42da94e0ca397ef0dfe9cedb
ARG PGJDBC_VERSION=42.7.12
ARG PGJDBC_SHA256=31fbf6f06b2217fb51d5100cee51b22625cc81640da0679b47914e54c1e6377c

FROM liquibase:${LIQUIBASE_VERSION}@${LIQUIBASE_IMAGE_DIGEST} AS liquibase-runtime

ARG PGJDBC_VERSION
ARG PGJDBC_SHA256

USER root
RUN curl --fail --silent --show-error --location \
        --output /liquibase/lib/postgresql.jar \
        "https://repo.maven.apache.org/maven2/org/postgresql/postgresql/${PGJDBC_VERSION}/postgresql-${PGJDBC_VERSION}.jar" \
    && echo "${PGJDBC_SHA256}  /liquibase/lib/postgresql.jar" | sha256sum --check --strict \
    && rm -f /liquibase/bin/lpm

USER liquibase:liquibase
