{{/* CloudPirates expects digests in image.tag; retain our separate image.digest API. */}}
{{- define "autowp.image" -}}
{{- $image := deepCopy .imageRoot -}}
{{- if $image.digest -}}
{{- $_ := set $image "tag" "" -}}
{{- printf "%s@%s" (include "cloudpirates.image" (dict "image" $image "global" .global) | trimSuffix ":") $image.digest -}}
{{- else -}}
{{- include "cloudpirates.image" (dict "image" $image "global" .global) -}}
{{- end -}}
{{- end -}}
