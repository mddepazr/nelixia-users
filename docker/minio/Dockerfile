FROM golang:1.24.6-alpine3.22 AS builder

RUN apk add --no-cache git

ENV CGO_ENABLED=0 \
    GOBIN=/out

# MinIO Community is distributed as source. Keep the version used by the
# previous Compose image so existing local volumes remain on the same release.
RUN go install github.com/minio/minio@RELEASE.2025-09-07T16-13-09Z

FROM alpine:3.22

RUN apk add --no-cache ca-certificates

COPY --from=builder /out/minio /usr/local/bin/minio

EXPOSE 9000 9001

ENTRYPOINT ["/usr/local/bin/minio"]
