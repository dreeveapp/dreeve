# Installation on Kubernetes

## Prerequisites

- A Kubernetes cluster with a CSI storage class capable of dynamic provisioning
- An Ingress controller (the example below assumes ingress-nginx; adjust
  `ingressClassName` and annotations for your controller)
- A Strava API application (create one at
  <https://www.strava.com/settings/api>) if importing via the Strava API
- DNS for your chosen hostname pointing at your Ingress

> [!WARNING]
> **Set your storage class.** The manifests below use
> `storageClassName: CHANGE-ME` on every PersistentVolumeClaim. Replace it
> with a class that exists on **your** cluster - check with
> `kubectl get storageclass`. Common examples: `longhorn`,
> `rook-ceph-block`, `openebs-lvmpv`, `local-path`, or your cloud
> provider's default.

## The manifests

Save the following as `dreeve.yaml`. Replace every `CHANGE-ME` value:
Strava credentials, the app secret, the admin password hash, the hostname,
and the storage class.

```yaml
---
apiVersion: v1
kind: Namespace
metadata:
  name: dreeve
---
# All app configuration. Kept as a Secret (not a ConfigMap) because it
# contains Strava credentials and the app secret.
apiVersion: v1
kind: Secret
metadata:
  name: dreeve-env
  namespace: dreeve
type: Opaque
stringData:
  # From your Strava API application settings
  STRAVA_CLIENT_ID: "CHANGE-ME"
  STRAVA_CLIENT_SECRET: "CHANGE-ME"
  # Obtained on first launch via the app's OAuth flow - NOT the refresh
  # token shown on the Strava API settings page. The app rotates it on
  # first run and persists it in the database volume thereafter.
  STRAVA_REFRESH_TOKEN: "CHANGE-ME"
  TZ: "Etc/GMT"
  IMPORT_MODE: "stravaApi"
  # Must match the hostname on your Ingress
  APP_URL: "https://dreeve.example.com/"
  PROXY_HOST: "https://dreeve.example.com"
  PROXY_PORT: "80"
  # Generate a long random string
  APP_SECRET: "CHANGE-ME"
  ADMIN_USERNAME: "admin"
  # bcrypt hash - generate per the Dreeve docs
  ADMIN_PASSWORD_HASH: "CHANGE-ME"
---
# Replaces the bind-mounted php.ini from the compose setup. Large
# imports are memory-hungry; 4G matches the upstream guidance.
apiVersion: v1
kind: ConfigMap
metadata:
  name: dreeve-php-ini
  namespace: dreeve
data:
  memory-limit.ini: |
    memory_limit = 4G
---
# ---------------------------------------------------------------------
# PersistentVolumeClaims - one per compose bind mount.
# RWO is fine because app + daemon run in the SAME pod (see Deployment),
# so they always share the node and the volume attachment.
# ---------------------------------------------------------------------
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  # Holds the render cache both containers share.
  name: dreeve-build
  namespace: dreeve
spec:
  accessModes: ["ReadWriteOnce"]
  storageClassName: CHANGE-ME   # <- kubectl get storageclass
  resources:
    requests:
      storage: 2Gi
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: dreeve-database
  namespace: dreeve
spec:
  accessModes: ["ReadWriteOnce"]
  storageClassName: CHANGE-ME   # <- kubectl get storageclass
  resources:
    requests:
      storage: 5Gi
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: dreeve-files
  namespace: dreeve
spec:
  accessModes: ["ReadWriteOnce"]
  storageClassName: CHANGE-ME   # <- kubectl get storageclass
  resources:
    requests:
      storage: 10Gi
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: dreeve-watch
  namespace: dreeve
spec:
  accessModes: ["ReadWriteOnce"]
  storageClassName: CHANGE-ME   # <- kubectl get storageclass
  resources:
    requests:
      storage: 1Gi
---
# ---------------------------------------------------------------------
# Deployment - app (Caddy/PHP web) + daemon as two containers in one
# pod. They share the same SQLite database, so co-locating them avoids
# RWX volumes and SQLite lock corruption. Recreate strategy ensures a
# rolling update never runs two copies against the same database.
# ---------------------------------------------------------------------
apiVersion: apps/v1
kind: Deployment
metadata:
  name: dreeve
  namespace: dreeve
  labels:
    app: dreeve
spec:
  replicas: 1
  strategy:
    type: Recreate
  selector:
    matchLabels:
      app: dreeve
  template:
    metadata:
      labels:
        app: dreeve
    spec:
      containers:
        - name: app
          image: robiningelbrecht/dreeve:latest
          imagePullPolicy: Always
          ports:
            - name: http
              containerPort: 8080
          envFrom:
            - secretRef:
                name: dreeve-env
          volumeMounts: &dreeve-mounts
            - name: build
              mountPath: /var/www/build
            - name: database
              mountPath: /var/www/storage/database
            - name: files
              mountPath: /var/www/storage/files
            - name: watch
              mountPath: /var/www/watch
            - name: php-ini
              mountPath: /usr/local/etc/php/conf.d/memory-limit.ini
              subPath: memory-limit.ini
          # Mirrors the upstream compose healthcheck. Must be an exec
          # probe: Caddy's admin API (:2019) binds to localhost only,
          # so an httpGet probe from the kubelet gets refused.
          readinessProbe:
            exec:
              command: ["curl", "-f", "http://localhost:2019/metrics"]
            initialDelaySeconds: 15
            periodSeconds: 10
            timeoutSeconds: 5
          livenessProbe:
            exec:
              command: ["curl", "-f", "http://localhost:2019/metrics"]
            initialDelaySeconds: 60
            periodSeconds: 30
            timeoutSeconds: 5
          resources:
            requests:
              cpu: 250m
              memory: 512Mi
            limits:
              memory: 5Gi   # headroom above the 4G PHP memory_limit
        - name: daemon
          image: robiningelbrecht/dreeve:latest
          imagePullPolicy: Always
          command: ["bin/console", "app:daemon:run"]
          envFrom:
            - secretRef:
                name: dreeve-env
          volumeMounts: *dreeve-mounts
          resources:
            requests:
              cpu: 250m
              memory: 512Mi
            limits:
              memory: 5Gi   # imports run here and are the memory-hungry path
      volumes:
        - name: build
          persistentVolumeClaim:
            claimName: dreeve-build
        - name: database
          persistentVolumeClaim:
            claimName: dreeve-database
        - name: files
          persistentVolumeClaim:
            claimName: dreeve-files
        - name: watch
          persistentVolumeClaim:
            claimName: dreeve-watch
        - name: php-ini
          configMap:
            name: dreeve-php-ini
---
apiVersion: v1
kind: Service
metadata:
  name: dreeve
  namespace: dreeve
  labels:
    app: dreeve
spec:
  type: ClusterIP
  selector:
    app: dreeve
  ports:
    - name: http
      port: 8080
      targetPort: 8080
---
# ---------------------------------------------------------------------
# Ingress - ingress-nginx example. TLS terminates here; the pod speaks
# plain HTTP on 8080, which matches PROXY_HOST=https + PROXY_PORT=80
# in the Secret above.
# ---------------------------------------------------------------------
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: dreeve
  namespace: dreeve
  annotations:
    # FIT/GPX uploads and photo imports via the admin panel can be large
    nginx.ingress.kubernetes.io/proxy-body-size: "512m"
    # If you use cert-manager, uncomment and set your issuer:
    # cert-manager.io/cluster-issuer: CHANGE-ME
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - dreeve.example.com
      secretName: dreeve-tls
  rules:
    - host: dreeve.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: dreeve
                port:
                  number: 8080
```

Apply it:

```bash
kubectl apply -f dreeve.yaml
kubectl -n dreeve get pods -w
```

Within about 30 seconds the pod should report `2/2 Running`.

## First run: import your data

Dreeve's first-run flow asks you to import your data. The compose commands
translate directly to `kubectl exec` - the `-c app` flag matters because the
pod has two containers:

```bash
kubectl -n dreeve exec deploy/dreeve -c app -- bin/console app:import:strava
```

Using `deploy/dreeve` saves looking up the pod name - kubectl resolves it to
the running pod.

## Operational notes

- **Memory.** The import step is the hungry one and grows with your activity
  count. The manifests set PHP's `memory_limit` to 4G with a 5Gi container
  ceiling. If you see OOMKills or failing imports as your history grows, bump
  both together (the ConfigMap and the container limit).
- **Never scale above 1 replica.** SQLite. The `Recreate` strategy exists
  for the same reason.
- **Upgrades.** `kubectl -n dreeve rollout restart deploy/dreeve` pulls the
  latest image (`imagePullPolicy: Always`). Pin a version tag instead of
  `latest` if you prefer deliberate upgrades - and snapshot or back up the
  database PVC before major version jumps.
- **Backups.** Everything that matters lives in the four PVCs, with the
  database volume being the critical one. SQLite snapshots taken at the
  storage layer are crash-consistent, which is generally fine for this
  workload.
