FROM haproxy:2.9-alpine

COPY haproxy/haproxy.cfg /usr/local/etc/haproxy/haproxy.cfg

EXPOSE 7451 8404

CMD ["haproxy", "-f", "/usr/local/etc/haproxy/haproxy.cfg"]
