import { drizzle } from "drizzle-orm/postgres-js";
import postgres from "postgres";
import * as schema from "./schema";

/**
 * Null when DATABASE_URL is absent, so the site still builds and renders
 * without a database — only the booking routes need one.
 */
let _db: ReturnType<typeof drizzle<typeof schema>> | null = null;

export function getDb() {
  if (_db) return _db;
  const url = process.env.DATABASE_URL;
  if (!url) return null;
  // One connection: serverless functions are short-lived and pool at the edge.
  const client = postgres(url, { max: 1 });
  _db = drizzle(client, { schema });
  return _db;
}

export { schema };
export * from "./schema";
