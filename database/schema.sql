SET NAMES utf8mb4;

CREATE TABLE customers (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(255)    NOT NULL,
    name        VARCHAR(255)    NULL,
    created_at  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),

    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- event_types — dictionary: 'purchase' -> 1, 'page_view' -> 2, ...
-- ---------------------------------------------------------------------
CREATE TABLE event_types (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(64)       NOT NULL,
    created_at  DATETIME(3)       NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- property_keys — dictionary: 'amount' -> 1, 'product' -> 2, ...
-- ---------------------------------------------------------------------
CREATE TABLE property_keys (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(64)       NOT NULL,
    created_at  DATETIME(3)       NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (id),
    UNIQUE KEY uq_property_keys_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE events (
    id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    customer_id    BIGINT UNSIGNED   NOT NULL,
    event_type_id  SMALLINT UNSIGNED NOT NULL,
    occurred_at    DATETIME(3)       NOT NULL COMMENT 'Client-provided timestamp (UTC)',
    received_at    DATETIME(3)       NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'Ingestion time (UTC)',
    properties     JSON              NULL COMMENT 'Raw payload, display only - never filtered on',
    dedup_hash     BINARY(32)        NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_events_dedup_hash (dedup_hash),
    KEY idx_events_customer_time (customer_id, occurred_at DESC, id DESC),
    KEY idx_events_type_time (event_type_id, occurred_at),

    CONSTRAINT fk_events_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_events_event_type
        FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- event_properties — one row per (event, property), typed for querying.
-- Deleting an event (or, by cascade, a customer) removes its properties.
-- ---------------------------------------------------------------------
CREATE TABLE event_properties (
    event_id         BIGINT UNSIGNED   NOT NULL,
    property_key_id  SMALLINT UNSIGNED NOT NULL,
    customer_id      BIGINT UNSIGNED   NOT NULL COMMENT 'Copy of events.customer_id',
    event_type_id    SMALLINT UNSIGNED NOT NULL COMMENT 'Copy of events.event_type_id',
    value_num        DECIMAL(20,6)     NULL,
    value_str        VARCHAR(255)      NULL,

    PRIMARY KEY (event_id, property_key_id),
    KEY idx_ep_type_key_num (event_type_id, property_key_id, value_num, customer_id),
    KEY idx_ep_type_key_str (event_type_id, property_key_id, value_str, customer_id),
    KEY idx_ep_property_key (property_key_id), -- required by fk_ep_property_key (FK column must lead an index)

    CONSTRAINT fk_ep_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_property_key
        FOREIGN KEY (property_key_id) REFERENCES property_keys (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE customer_event_stats (
    customer_id    BIGINT UNSIGNED   NOT NULL,
    event_type_id  SMALLINT UNSIGNED NOT NULL,
    event_count    INT UNSIGNED      NOT NULL DEFAULT 0,
    total_amount   DECIMAL(20,6)     NOT NULL DEFAULT 0,
    first_at       DATETIME(3)       NOT NULL,
    last_at        DATETIME(3)       NOT NULL,

    PRIMARY KEY (customer_id, event_type_id),
    KEY idx_stats_type_count (event_type_id, event_count),
    KEY idx_stats_type_amount (event_type_id, total_amount),

    CONSTRAINT fk_stats_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_event_type
        FOREIGN KEY (event_type_id) REFERENCES event_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE api_keys (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)  NOT NULL,
    key_hash      BINARY(32)    NOT NULL,
    created_at    DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    last_used_at  DATETIME(3)   NULL,
    revoked_at    DATETIME(3)   NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_api_keys_key_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
