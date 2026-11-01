#!/bin/sh
# Creates the S3 buckets given as arguments and makes each one anonymously readable
# (the equivalent of `mc anonymous set download`). Runs in the amazon/aws-cli image
# against the S3 service; AWS_ENDPOINT_URL and credentials come from the environment.

set -e

until aws s3api list-buckets > /dev/null 2>&1; do
  echo 'Wait S3 to startup...'
  sleep 1
done

for bucket in "$@"; do
  if ! aws s3api head-bucket --bucket "$bucket" > /dev/null 2>&1; then
    aws s3api create-bucket --bucket "$bucket"
  fi

  aws s3api put-bucket-policy --bucket "$bucket" --policy "{
    \"Version\": \"2012-10-17\",
    \"Statement\": [
      {
        \"Effect\": \"Allow\",
        \"Principal\": {\"AWS\": [\"*\"]},
        \"Action\": [\"s3:GetBucketLocation\"],
        \"Resource\": [\"arn:aws:s3:::$bucket\"]
      },
      {
        \"Effect\": \"Allow\",
        \"Principal\": {\"AWS\": [\"*\"]},
        \"Action\": [\"s3:GetObject\"],
        \"Resource\": [\"arn:aws:s3:::$bucket/*\"]
      }
    ]
  }"
done
