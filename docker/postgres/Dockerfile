ARG POSTGRES_VERSION=18-alpine
ARG POSTGRES_IMAGE_DIGEST=sha256:b6972e1115a4ff8eba5d7f7d19184926d1e16d7d1136ae1e1a7ebf286da0f015
ARG AGE_PACKAGE_VERSION=1.3.2-r1

FROM postgres:${POSTGRES_VERSION}@${POSTGRES_IMAGE_DIGEST} AS postgres-runtime

ARG OS_PACKAGE_REFRESH=2026-11-11

USER root
RUN test -n "$OS_PACKAGE_REFRESH" \
    && apk upgrade --no-cache \
    && rm -f /usr/local/bin/gosu

COPY --chmod=0755 docker/postgres/init-roles.sh /docker-entrypoint-initdb.d/10-init-roles.sh
COPY --chmod=0755 docker/postgres/sync-role-passwords.sh /usr/local/bin/sync-role-passwords
COPY --chmod=0755 docker/postgres/finalize-role-hardening.sh /usr/local/bin/finalize-role-hardening

USER postgres

FROM postgres-runtime AS backup-runtime

ARG AGE_PACKAGE_VERSION

USER root
RUN apk add --no-cache "age=${AGE_PACKAGE_VERSION}"

COPY docker/postgres/backup.sh /usr/local/bin/benevole-jambville-backup
COPY docker/postgres/verify-backup.sh /usr/local/bin/benevole-jambville-verify-backup
RUN chmod 0755 \
    /usr/local/bin/benevole-jambville-backup \
    /usr/local/bin/benevole-jambville-verify-backup

USER postgres
ENTRYPOINT ["/usr/local/bin/benevole-jambville-backup"]
