#!/bin/bash
RPW=$(grep MULTICHAIN_RPC_PASSWORD /var/app/current/.env | cut -d= -f2)
CHAIRMAN_IP=$(grep MULTICHAIN_NODE_BAC_CHAIRMAN_PRIVATE_IP /var/app/current/.env | cut -d= -f2)
CHAIRMAN_PORT=$(grep MULTICHAIN_NODE_BAC_CHAIRMAN_RPC_PORT /var/app/current/.env | cut -d= -f2)
echo "Chairman: $CHAIRMAN_IP:$CHAIRMAN_PORT"
timeout 15 curl -s -u multichainrpc:$RPW \
  -d '{"id":1,"method":"liststreamitems","params":["procurement.metadata",true,2,0,false]}' \
  "http://$CHAIRMAN_IP:$CHAIRMAN_PORT" 2>&1 | head -1
