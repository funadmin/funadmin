FROM docker.m.daocloud.io/library/php@sha256:6cb44388b6ffc8c9a35b4cfdf518d0565e0cf9150e479add338b498874d7e971

ARG COMPOSER_VERSION=2.8.8
ARG COMPOSER_SHA256=957263e284b9f7a13d7f475dc65f3614d151b0c4dcc7e8761f7e7f749447fb68
ARG NODEJS_VERSION=20.19.2+dfsg-1+deb13u2
ARG NPM_VERSION=9.2.0~ds1-3
ARG GIT_VERSION=1:2.47.3-0+deb13u1
ARG RIPGREP_VERSION=14.1.1-1+b4

RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::Retries=3 update \
    && apt-get install -y --no-install-recommends ca-certificates "nodejs=${NODEJS_VERSION}" "npm=${NPM_VERSION}" "git=${GIT_VERSION}" "ripgrep=${RIPGREP_VERSION}" \
    && php -r "copy('https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar', '/tmp/composer.phar');" \
    && echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c - \
    && install -m 0555 /tmp/composer.phar /usr/local/bin/composer \
    && rm /tmp/composer.phar \
    && php --version \
    && composer --version \
    && node --version \
    && npm --version \
    && npx --version \
    && git --version \
    && rg --version \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 agent \
    && useradd --uid 10001 --gid 10001 --no-create-home --shell /usr/sbin/nologin agent

COPY --chmod=0555 entrypoint.sh /usr/local/bin/ai-agent-entrypoint
WORKDIR /workspace
USER 10001:10001
ENTRYPOINT ["/usr/local/bin/ai-agent-entrypoint"]
CMD ["sleep", "infinity"]
