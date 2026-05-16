#!/bin/bash
# Check subscription status on all nodes
RPW=$(grep MULTICHAIN_RPC_PASSWORD /var/app/current/.env | cut -d= -f2)

for NODE in "admin:172.31.13.41:6834" "hope:172.31.42.5:6834" "bac-chairman:172.31.47.250:6834" "bac-secretariat:172.31.0.186:6834"; do
  NAME=$(echo $NODE | cut -d: -f1)
  IP=$(echo $NODE | cut -d: -f2)
  PORT=$(echo $NODE | cut -d: -f3)
  RESULT=$(timeout 8 curl -s -u multichainrpc:$RPW \
    -d '{"id":1,"method":"liststreamitems","params":["procurement.metadata",true,1,0,false]}' \
    "http://$IP:$PORT" 2>&1)
  echo "$NAME ($IP): $RESULT" | head -1
done
