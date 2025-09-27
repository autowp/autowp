FROM docker.io/library/ubuntu:plucky AS builder

ENV HOME=/root
ENV PATH=$PATH:/usr/local/go/bin:$HOME/go/bin \
    GOPATH=$HOME/go \
    DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get -qqy --no-install-recommends install \
        build-essential \
        ca-certificates \
        libaom3 \
        imagemagick \
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
        libmagickwand-dev \
        libde265-0 \
        pkg-config \
        tzdata \
        wget \
    && \
    wget -q -nv https://go.dev/dl/go1.24.3.linux-amd64.tar.gz && \
    rm -rf /usr/local/go && \
    tar -C /usr/local -xzf go1.24.3.linux-amd64.tar.gz && \
    rm go1.24.3.linux-amd64.tar.gz && \
    apt-get -qqy remove wget && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

WORKDIR $GOPATH/src/github.com/autowp/goautowp/

# Populate the module cache based on the go.{mod,sum} files.
COPY go.mod go.sum ./
RUN go mod download && \
    go mod verify

COPY . $GOPATH/src/github.com/autowp/goautowp/

RUN CGO_ENABLED=1 GOOS=linux GOARCH=amd64 go build -ldflags="-w -s" -o /goautowp cmd/goautowp/goautowp.go

###########################
FROM docker.io/library/ubuntu:plucky

LABEL app-name="autowp.goautowp" \
      maintainer="dmitry@pereslegin.ru"

ENV USER=appuser \
    UID=10001 \
    DEBIAN_FRONTEND=noninteractive

EXPOSE 8080

ENTRYPOINT ["/goautowp"]

WORKDIR /

RUN apt-get update && \
    apt-get -qqy --no-install-recommends install \
        adduser \
        ca-certificates \
        libaom3 \
        imagemagick \
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
    # See https://stackoverflow.com/a/55757473/12429735RUN
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

USER appuser:appuser

COPY --from=builder /goautowp /goautowp
COPY migrations /migrations
COPY postgres-migrations /postgres-migrations
COPY defaults.yaml /defaults.yaml
