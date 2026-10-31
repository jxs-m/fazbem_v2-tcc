#!/usr/bin/env bash
# Caminho: scripts/ci.sh
# Pipeline de Integração Contínua (CI) e Execução Isolada de Testes — FazBem V2 (Fase 5)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "=================================================================="
echo " FAZBEM V2 — PIPELINE DE CI E SUÍTE COMPLETA DE TESTES (FASE 5)"
echo "=================================================================="

# 1. Lint PHP e Análise Estática
echo ""
echo ">>> [ETAPA 1/3] Executando Lint PHP e Análise Estática..."
php tests/static_analysis.php

# 2. Preparar banco de dados isolado de testes (fazbem_v2_test)
EPHEMERAL_DB_DIR=""
cleanup() {
    if [[ -n "$EPHEMERAL_DB_DIR" && -d "$EPHEMERAL_DB_DIR" ]]; then
        if [[ -S "$EPHEMERAL_DB_DIR/mysql.sock" ]]; then
            mysqladmin --socket="$EPHEMERAL_DB_DIR/mysql.sock" -u root shutdown >/dev/null 2>&1 || true
        fi
        rm -rf "$EPHEMERAL_DB_DIR"
    fi
}
trap cleanup EXIT

export TEST_DB_HOST="${TEST_DB_HOST:-127.0.0.1}"
export TEST_DB_PORT="${TEST_DB_PORT:-3306}"
export TEST_DB_USER="${TEST_DB_USER:-root}"
export TEST_DB_PASS="${TEST_DB_PASS:-}"
export TEST_DB_SOCKET="${TEST_DB_SOCKET:-}"
export DB_NAME="fazbem_v2_test"
export AMBIENTE="test"

# Verifica se já existe um servidor MySQL/MariaDB acessível; caso contrário, sobe instância efêmera isolada
DB_REACHABLE=0
if php -r '
    $s = getenv("TEST_DB_SOCKET");
    $h = getenv("TEST_DB_HOST") ?: "127.0.0.1";
    $p = getenv("TEST_DB_PORT") ?: "3306";
    $u = getenv("TEST_DB_USER") ?: "root";
    $pw = getenv("TEST_DB_PASS") !== false ? getenv("TEST_DB_PASS") : "";
    $dsn = $s ? "mysql:unix_socket={$s}" : "mysql:host={$h};port={$p}";
    try { new PDO($dsn, $u, $pw); exit(0); } catch (Throwable $e) { exit(1); }
' >/dev/null 2>&1; then
    DB_REACHABLE=1
fi

if [[ "$DB_REACHABLE" -eq 0 ]]; then
    echo ""
    echo ">>> Inicializando instância MariaDB efêmera isolada para execução dos testes..."
    EPHEMERAL_DB_DIR="/tmp/fazbem_ci_mysql_$$"
    mkdir -p "$EPHEMERAL_DB_DIR"
    mysql_install_db --no-defaults --datadir="$EPHEMERAL_DB_DIR" --auth-root-authentication-method=normal >/dev/null 2>&1
    mariadbd --no-defaults \
        --datadir="$EPHEMERAL_DB_DIR" \
        --socket="$EPHEMERAL_DB_DIR/mysql.sock" \
        --pid-file="$EPHEMERAL_DB_DIR/mysql.pid" \
        --skip-networking \
        >"$EPHEMERAL_DB_DIR/mysqld.log" 2>&1 &

    for _ in $(seq 1 50); do
        if mysqladmin --socket="$EPHEMERAL_DB_DIR/mysql.sock" -u root ping >/dev/null 2>&1; then
            break
        fi
        sleep 0.2
    done

    export TEST_DB_SOCKET="$EPHEMERAL_DB_DIR/mysql.sock"
    export DB_SOCKET="$EPHEMERAL_DB_DIR/mysql.sock"
    export TEST_DB_USER="root"
    export TEST_DB_PASS=""
fi

export DB_HOST="$TEST_DB_HOST"
export DB_PORT="$TEST_DB_PORT"
export DB_USER="$TEST_DB_USER"
export DB_PASS="$TEST_DB_PASS"
export DB_SOCKET="$TEST_DB_SOCKET"

# 3. Executar suítes de testes automatizados (Fases 1 a 5) e validação de migrações
echo ""
echo ">>> [ETAPA 2/3] Executando Suítes de Testes e Validação de Migrações (Fases 1 a 4)..."
php tests/fase1_integridade_financeira_test.php
php tests/fase2_autorizacao_estados_test.php
php tests/fase3_estoque_concorrencia_test.php
php tests/fase4_conta_preservacao_test.php

echo ""
echo ">>> [ETAPA 3/3] Executando Suíte de Testes Unitários, Integração e Segurança (Fase 5)..."
php tests/fase5_testes_seguranca_integracao_test.php

echo ""
echo "=================================================================="
echo " PIPELINE DE CI CONCLUÍDO COM SUCESSO (0 FALHAS)"
echo "=================================================================="
