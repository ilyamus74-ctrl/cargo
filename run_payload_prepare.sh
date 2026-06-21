#!/usr/bin/env bash
set -u

WEB_DIR="/home/cells/web"
PHP="/usr/bin/php"
APACHE_USER="apache"

CLIENT_LIMIT="${CLIENT_LIMIT:-300}"
ASER_CLIENT_LIMIT="${ASER_CLIENT_LIMIT:-$CLIENT_LIMIT}"
PAYLOAD_LIMIT="${PAYLOAD_LIMIT:-300}"

ENABLE_COLIBRI_DETAILS="${ENABLE_COLIBRI_DETAILS:-1}"
ENABLE_ASER_DETAILS="${ENABLE_ASER_DETAILS:-1}"
ENABLE_COLIBRI_PAYLOAD="${ENABLE_COLIBRI_PAYLOAD:-1}"
ENABLE_ASER_PAYLOAD="${ENABLE_ASER_PAYLOAD:-1}"

LOG_DIR="$WEB_DIR/storage/logs"
LOG_FILE="$LOG_DIR/payload_prepare_$(date +%Y%m%d_%H%M%S).log"
LOCK_FILE="/tmp/cargo_payload_prepare.lock"

mkdir -p "$LOG_DIR"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "Another payload prepare run is already active. Exit."
  exit 1
fi

cd "$WEB_DIR" || exit 1

run_step() {
  local title="$1"
  shift

  echo
  echo "============================================================" | tee -a "$LOG_FILE"
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] START: $title" | tee -a "$LOG_FILE"
  echo "CMD: $*" | tee -a "$LOG_FILE"
  echo "============================================================" | tee -a "$LOG_FILE"

  "$@" 2>&1 | tee -a "$LOG_FILE"
  local rc="${PIPESTATUS[0]}"

  echo "[$(date '+%Y-%m-%d %H:%M:%S')] END: $title, rc=$rc" | tee -a "$LOG_FILE"

  return "$rc"
}

FAILED=0

echo "Payload prepare run started at $(date '+%Y-%m-%d %H:%M:%S')" | tee -a "$LOG_FILE"
echo "WEB_DIR=$WEB_DIR" | tee -a "$LOG_FILE"
echo "CLIENT_LIMIT=$CLIENT_LIMIT" | tee -a "$LOG_FILE"
echo "ASER_CLIENT_LIMIT=$ASER_CLIENT_LIMIT" | tee -a "$LOG_FILE"
echo "PAYLOAD_LIMIT=$PAYLOAD_LIMIT" | tee -a "$LOG_FILE"

if [ "$ENABLE_COLIBRI_DETAILS" = "1" ]; then
  run_step "COLIBRI missing client details" \
    sudo -u "$APACHE_USER" "$PHP" www/scripts/mvp/app/Forwarder/run_package_details.php \
      --connector-id=2 \
      --source=missing-clients \
      --limit="$CLIENT_LIMIT" || FAILED=$((FAILED+1))
else
  echo "SKIP: COLIBRI missing client details" | tee -a "$LOG_FILE"
fi

if [ "$ENABLE_ASER_DETAILS" = "1" ]; then
  run_step "ASER missing client details" \
    sudo -u "$APACHE_USER" "$PHP" www/scripts/mvp/app/Forwarder/run_package_details.php \
      --connector-id=7 \
      --source=missing-clients \
      --limit="$ASER_CLIENT_LIMIT" || FAILED=$((FAILED+1))
else
  echo "SKIP: ASER missing client details" | tee -a "$LOG_FILE"
fi

if [ "$ENABLE_COLIBRI_PAYLOAD" = "1" ]; then
  run_step "COLIBRI warehouse label payloads" \
    sudo -u "$APACHE_USER" "$PHP" www/scripts/warehouse/prepare_out_label_payloads.php \
      --connector-id=2 \
      --limit="$PAYLOAD_LIMIT" || FAILED=$((FAILED+1))
else
  echo "SKIP: COLIBRI warehouse label payloads" | tee -a "$LOG_FILE"
fi

if [ "$ENABLE_ASER_PAYLOAD" = "1" ]; then
  run_step "ASER warehouse label payloads" \
    sudo -u "$APACHE_USER" "$PHP" www/scripts/warehouse/prepare_out_label_payloads.php \
      --connector-id=7 \
      --limit="$PAYLOAD_LIMIT" || FAILED=$((FAILED+1))
else
  echo "SKIP: ASER warehouse label payloads" | tee -a "$LOG_FILE"
fi

echo
echo "============================================================" | tee -a "$LOG_FILE"
echo "Finished. Failed steps: $FAILED" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE" | tee -a "$LOG_FILE"
echo "============================================================" | tee -a "$LOG_FILE"

exit "$FAILED"
