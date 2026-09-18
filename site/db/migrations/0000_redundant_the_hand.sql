CREATE TYPE "public"."booking_status" AS ENUM('pending', 'paid', 'cancelled', 'refunded');--> statement-breakpoint
CREATE TABLE "bookings" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"product" text NOT NULL,
	"dives" integer NOT NULL,
	"dive_date" date NOT NULL,
	"divers" integer NOT NULL,
	"certification" text NOT NULL,
	"name" text NOT NULL,
	"email" text NOT NULL,
	"locale" text DEFAULT 'en' NOT NULL,
	"total_usd_cents" integer NOT NULL,
	"deposit_usd_cents" integer NOT NULL,
	"deposit_mxn_cents" integer NOT NULL,
	"status" "booking_status" DEFAULT 'pending' NOT NULL,
	"preference_id" text,
	"payment_id" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"paid_at" timestamp with time zone
);
--> statement-breakpoint
CREATE INDEX "bookings_preference_idx" ON "bookings" USING btree ("preference_id");--> statement-breakpoint
CREATE INDEX "bookings_date_idx" ON "bookings" USING btree ("dive_date","status");--> statement-breakpoint
CREATE INDEX "bookings_email_idx" ON "bookings" USING btree ("email");