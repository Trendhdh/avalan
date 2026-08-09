-- =====================================================================
-- Avalan SmartPay — reference schema (DEMO)
-- =====================================================================
-- This is a trimmed-down illustration of the real data model, kept for
-- reviewers who want to see the shape of the tables the production
-- repositories query. It is NOT required to run this demo: the demo API
-- reads a single fixture user from database/seed_demo.json instead of
-- MySQL, so no database setup is needed at all.
--
-- Production's real schema lives in 90+ incremental migration files
-- (backend/avalan-smartpay/database/001_schema.sql through 091_*.sql in
-- the production project) covering loans, goals, wallets, subscriptions,
-- card monitoring, gamification, and more. Only the tables relevant to
-- the engines shown in this demo (Balance, Liability, Risk, Score,
-- SmartPay/PaymentAllocation) are included below.
-- =====================================================================

CREATE TABLE users (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    full_name     VARCHAR(191) NOT NULL,
    phone         VARCHAR(32)  NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cards (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    user_id           INT NOT NULL,
    bank              VARCHAR(64) NOT NULL,
    card_last4        CHAR(4) NOT NULL,
    paylov_card_token VARCHAR(191) NOT NULL, -- opaque token, never the real PAN
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Production caches live Paylov card balances here (migration 085) so
-- BalanceEngine reads MySQL instead of calling the open-banking API on
-- every request. The demo's BalanceEngine reads balance_minor straight
-- from the fixture instead of this cache.
CREATE TABLE card_balance_cache (
    paylov_card_token VARCHAR(191) PRIMARY KEY,
    balance_minor     BIGINT NOT NULL,
    currency          CHAR(3) NOT NULL DEFAULT 'UZS',
    updated_at        DATETIME NOT NULL
);

CREATE TABLE cash_accounts (
    user_id       INT NOT NULL,
    amount_minor  BIGINT NOT NULL,
    currency      CHAR(3) NOT NULL DEFAULT 'UZS',
    effective_at  DATETIME NOT NULL,
    PRIMARY KEY (user_id, effective_at)
);

CREATE TABLE loans (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    user_id               INT NOT NULL,
    lender_name           VARCHAR(191) NOT NULL,
    principal_minor       BIGINT NOT NULL,
    remaining_minor       BIGINT NOT NULL,
    monthly_payment_minor BIGINT NOT NULL,
    interest_rate         DECIMAL(5,2) NOT NULL,
    term_months           SMALLINT NOT NULL,
    months_paid           SMALLINT NOT NULL DEFAULT 0,
    status                ENUM('active','closed') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Unified upcoming-obligation source LiabilityEngine reads from — one
-- row per due date, covering both loan installments and everything else
-- (utilities, taxes, subscriptions, manual entries).
CREATE TABLE payment_schedule (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    user_id       INT NOT NULL,
    source_type   ENUM('loan_installment','payment_schedule') NOT NULL,
    source_id     INT NOT NULL,
    category      VARCHAR(32) NOT NULL,
    label         VARCHAR(191) NOT NULL,
    amount_minor  BIGINT NOT NULL,
    due_date      DATE NOT NULL,
    is_mandatory  TINYINT(1) NOT NULL DEFAULT 1,
    status        ENUM('pending','paid','skipped') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Append-only audit trail — every RiskEngine::evaluate() call inserts
-- one immutable row here (see RiskEngine::logRisk()).
CREATE TABLE risk_logs (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    total_balance_minor BIGINT NOT NULL,
    reserved_minor      BIGINT NOT NULL,
    available_minor     BIGINT NOT NULL,
    debt_ratio          DECIMAL(6,4) NOT NULL,
    reserve_ratio       DECIMAL(6,4) NOT NULL,
    liquidity_ratio     DECIMAL(6,4) NOT NULL,
    emergency_days      DECIMAL(8,2) NOT NULL,
    stress_score        DECIMAL(5,2) NOT NULL,
    confidence_score    DECIMAL(5,2) NOT NULL,
    doubt_score         DECIMAL(5,2) NOT NULL,
    crisis_mode         TINYINT(1) NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Append-only Financial Score history — one row per ScoreEngine recompute.
CREATE TABLE score_snapshots (
    id                        BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id                   INT NOT NULL,
    rating                    SMALLINT NOT NULL,
    rank_code                 CHAR(1) NOT NULL,
    income_stability_score    DECIMAL(5,2) NOT NULL,
    financial_health_score    DECIMAL(5,2) NOT NULL,
    payment_discipline_score  DECIMAL(5,2) NOT NULL,
    resilience_score          DECIMAL(5,2) NOT NULL,
    data_confidence_score     DECIMAL(5,2) NOT NULL,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
