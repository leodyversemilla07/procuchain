#!/bin/bash
RPW=$(grep MULTICHAIN_RPC_PASSWORD /var/app/current/.env | cut -d= -f2)
for VAR_NAME in ADMIN BAC_SECRETARIAT BAC_CHAIRMAN HOPE; do
  IP=$(grep "MULTICHAIN_NODE_${VAR_NAME}_PRIVATE_IP" /var/app/current/.env | cut -d= -f2)
  PORT=$(grep "MULTICHAIN_NODE_${VAR_NAME}_RPC_PORT" /var/app/current/.env | cut -d= -f2)
  # Just get streaminfo - subscribed flag and item count
  RESULT=$(timeout 30 curl -s -u multichainrpc:$RPW -d \
    '{ "id":1, "method":"getstreaminfo", "params":["procurement.metadata"] }' \
    http://$IP:$PORT 2>/dev/null)
  SUBSCRIBED=$(echo "$RESULT" | python3 -c "
import sys,json
d=json.load(sys.stdin)
if d.get('error'):
  if d['error'].get('code')==-703:
    print('NOT_SUBSCRIBED')
  else:
    print('ERROR:'+str(d['error'].get('message','unknown')))
elif d.get('result'):
  r=d['result']
  print(f\"SUBSCRIBED(items={r.get('items','?')},subscribed={r.get('subscribed','?')})\")
else:
  print('NO_RESPONSE')
" 2>/dev/null || echo "TIMEOUT")
  echo "$VAR_NAME ($IP:$PORT): $SUBSCRIBED"
done
