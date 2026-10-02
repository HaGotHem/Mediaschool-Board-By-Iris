FROM node:22-alpine AS assets
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build
FROM nginx:1.28-alpine
COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /build/dist/ /usr/share/nginx/html/
