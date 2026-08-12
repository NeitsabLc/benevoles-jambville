--liquibase formatted sql

--changeset benevole-jambville:privileges runOnChange:true
GRANT CONNECT ON DATABASE benevole_jambville TO benevole_jambville_app;
GRANT USAGE ON SCHEMA benevole_jambville TO benevole_jambville_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA benevole_jambville TO benevole_jambville_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA benevole_jambville TO benevole_jambville_app;

GRANT CONNECT ON DATABASE benevole_jambville TO benevole_jambville_migrator;
GRANT USAGE, CREATE ON SCHEMA benevole_jambville, public TO benevole_jambville_migrator;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA benevole_jambville, public TO benevole_jambville_migrator;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA benevole_jambville, public TO benevole_jambville_migrator;

GRANT CONNECT ON DATABASE benevole_jambville TO benevole_jambville_backup;
GRANT USAGE ON SCHEMA benevole_jambville TO benevole_jambville_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA benevole_jambville TO benevole_jambville_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA benevole_jambville TO benevole_jambville_backup;
GRANT USAGE ON SCHEMA public TO benevole_jambville_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO benevole_jambville_backup;

ALTER DEFAULT PRIVILEGES IN SCHEMA benevole_jambville
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO benevole_jambville_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA benevole_jambville
    GRANT USAGE, SELECT ON SEQUENCES TO benevole_jambville_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA benevole_jambville
    GRANT SELECT ON TABLES TO benevole_jambville_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA benevole_jambville
    GRANT SELECT ON SEQUENCES TO benevole_jambville_backup;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO benevole_jambville_backup;

ALTER DEFAULT PRIVILEGES FOR ROLE benevole_jambville_migrator IN SCHEMA benevole_jambville
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO benevole_jambville_app;
ALTER DEFAULT PRIVILEGES FOR ROLE benevole_jambville_migrator IN SCHEMA benevole_jambville
    GRANT USAGE, SELECT ON SEQUENCES TO benevole_jambville_app;
ALTER DEFAULT PRIVILEGES FOR ROLE benevole_jambville_migrator IN SCHEMA benevole_jambville
    GRANT SELECT ON TABLES TO benevole_jambville_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE benevole_jambville_migrator IN SCHEMA benevole_jambville
    GRANT SELECT ON SEQUENCES TO benevole_jambville_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE benevole_jambville_migrator IN SCHEMA public
    GRANT SELECT ON TABLES TO benevole_jambville_backup;

--rollback REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA benevole_jambville FROM benevole_jambville_app, benevole_jambville_migrator, benevole_jambville_backup;
