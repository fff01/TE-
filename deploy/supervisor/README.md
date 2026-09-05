# Container service management

The target TE-KG environment is a Docker container whose PID 1 is supervisord.
Do not install or invoke the systemd unit in `deploy/neo4j` inside this
container.

The active supervisor process was launched with
`/etc/supervisor/conf.d/supervisord.conf`. That file currently defines SSH and
MySQL directly and does not include other files. An administrator must either
add the contents of `tekg-neo4j.conf` to that active configuration or add a
dedicated include entry, then use `supervisorctl reread` and
`supervisorctl update`. Do not replace the active file without preserving its
existing SSH and MySQL program definitions.

Neo4j's own logs remain under `/app/tekg/shared/logs/neo4j`. Apache is managed
with `apache2ctl` in this container rather than `systemctl`.
