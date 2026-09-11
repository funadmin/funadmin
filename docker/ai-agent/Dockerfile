FROM docker.m.daocloud.io/library/php@sha256:6cb44388b6ffc8c9a35b4cfdf518d0565e0cf9150e479add338b498874d7e971

ARG GIT_VERSION=1:2.47.3-0+deb13u1
ARG RIPGREP_VERSION=14.1.1-1+b4

RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Retries=3 update \
    && apt-get install -y --no-install-recommends "git=${GIT_VERSION}" "ripgrep=${RIPGREP_VERSION}" \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 agent \
    && useradd --uid 10001 --gid 10001 --no-create-home --shell /usr/sbin/nologin agent

COPY --chmod=0555 entrypoint.sh /usr/local/bin/ai-agent-entrypoint
WORKDIR /workspace
USER 10001:10001
ENTRYPOINT ["/usr/local/bin/ai-agent-entrypoint"]
CMD ["sleep", "infinity"]
