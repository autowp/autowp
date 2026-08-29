FROM docker.io/library/ubuntu:26.04

LABEL app-name="autowp.goautowp" \
      maintainer="dmitry@pereslegin.ru"

ENV USER=appuser \
    UID=10001 \
    DEBIAN_FRONTEND=noninteractive

EXPOSE 8080

ENTRYPOINT ["/goautowp"]

WORKDIR /

RUN apt-get update -qqy && \
    apt-get -qqy --no-install-recommends install \
        adduser \
        ca-certificates \
        libaom3 \
        imagemagick-7.q16 \
        imagemagick-7.q16hdri \
        libheif-plugin-aomdec \
        libheif-plugin-aomenc \
        libheif-plugin-dav1d \
        libheif-plugin-ffmpegdec \
        libheif-plugin-j2kdec \
        libheif-plugin-j2kenc \
        libheif-plugin-jpegdec \
        libheif-plugin-jpegenc \
        libheif-plugin-kvazaar \
        libheif-plugin-libde265 \
        libheif-plugin-rav1e \
        libheif-plugin-svtenc \
        libheif-plugin-x265 \
        libheif1 \
        libltdl7 \
        libde265-0 \
        pkg-config \
        tzdata \
    && \
    # See https://stackoverflow.com/a/55757473/12429735RUN \
    adduser \
        --disabled-password \
        --gecos "" \
        --home "/nonexistent" \
        --shell "/sbin/nologin" \
        --no-create-home \
        --uid "${UID}" \
        "${USER}" \
     && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY etc/ImageMagick-7/policy.xml /etc/ImageMagick-7/policy.xml

USER 10001:10001

COPY goautowp /goautowp
COPY migrations /migrations
COPY defaults.yaml /defaults.yaml
COPY THIRD_PARTY_LICENSES.txt /THIRD_PARTY_LICENSES.txt
