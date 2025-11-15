{{- define "imagePullSecret" }}
{{- with .Values.imageCredentials }}
{{- printf "{\"auths\":{\"%s\":{\"username\":\"%s\",\"password\":\"%s\",\"email\":\"%s\",\"auth\":\"%s\"}}}" .registry .username .password .email (printf "%s:%s" .username .password | b64enc) | b64enc }}
{{- end }}
{{- end }}

{{- define "localImagePullSecret" }}
{{- with .Values.localImageCredentials }}
{{- printf "{\"auths\":{\"%s\":{\"username\":\"%s\",\"password\":\"%s\",\"email\":\"%s\",\"auth\":\"%s\"}}}" .registry .username .password .email (printf "%s:%s" .username .password | b64enc) | b64enc }}
{{- end }}
{{- end }}

{{- define "autowp.imagePullSecrets" -}}
{{- include "common.images.pullSecrets" (dict "images" (list .Values.frontend.image .Values.goautowp.image) "global" .Values.global) -}}
{{- end -}}

{{- define "autowp.frontend.image" -}}
{{ include "common.images.image" (dict "imageRoot" .Values.frontend.image "global" .Values.global) }}
{{- end -}}

{{- define "autowp.goautowp.fullname" -}}
{{- printf "%s-goautowp" (include "common.names.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.scheduler-daily.fullname" -}}
{{- printf "%s-scheduler-daily" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.pictures-clear-queue.fullname" -}}
{{- printf "%s-pictures-clear-queue" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.scheduler-hourly.fullname" -}}
{{- printf "%s-scheduler-hourly" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.scheduler-midnight.fullname" -}}
{{- printf "%s-scheduler-midnight" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.scheduler-generate-index-cache.fullname" -}}
{{- printf "%s-scheduler-generate-index-cache" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.serve.fullname" -}}
{{- printf "%s-serve" (include "autowp.goautowp.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.goautowp.image" -}}
{{ include "common.images.image" (dict "imageRoot" .Values.goautowp.image "global" .Values.global) }}
{{- end -}}

{{- define "autowp.static.fullname" -}}
{{- printf "%s-static" (include "common.names.fullname" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "autowp.static.image" -}}
{{ include "common.images.image" (dict "imageRoot" .Values.static.image "global" .Values.global) }}
{{- end -}}
