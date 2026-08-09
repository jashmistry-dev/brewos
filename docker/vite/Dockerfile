FROM node:20-alpine

WORKDIR /app

# Install dependencies first (layer caching)
COPY backend/package*.json ./

RUN npm install

# The rest of the source is mounted as a volume at runtime
EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
