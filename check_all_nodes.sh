#!/bin/bash
RPW=$(grep MULTICHAIN_RPC_PASSWORD /var/app/current/.env | cut -d= -f2)
for VAR_NAME in ADMIN BAC_SECRETARIAT BAC_CHAIRMAN HOPE; do
  IP=$(grep "MULTICHAIN_NODE_${VAR_NAME}_PRIVATE_IP" /var/app/current/.env | cut -d= -f2)
  PORT=$(grep "MULTICHAIN_NODE_${VAR_NAME}_RPC_PORT" /var/app/current/.env | cut -d= -f2)
  if [ -z "$IP" ]; then continue; fi
  RESULT=$(timeout 10 curl -s -u multichainrpc:$RPW \
    -d '{"id":1,"method":"liststreamitems","params":["procurement.metadata",true,1,0,false]}' \
    "http://$IP:$PORT" 2>&1)
  CODE=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('error',{}).get('code','OK'))" 2>/dev/null || echo "TIMEOUT")
  echo "$VAR_NAME ($IP:$PORT): error=$CODE"
done
