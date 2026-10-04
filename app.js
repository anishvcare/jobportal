// Startup file for hosts that run the pre-built Next.js standalone bundle
// produced by .github/workflows/deploy-frontend.yml (e.g. cPanel
// "Setup Node.js App" / Phusion Passenger on CloudLinux).
//
// Layout of the deploy branch:
//   app.js        <- this file (the host's "Application startup file")
//   bundle/       <- Next standalone output: server.js, .next/, public/,
//                    and its own trimmed node_modules/
//
// The bundle lives in a subfolder because the CloudLinux Node.js selector
// manages a `node_modules` symlink in the application root and refuses a
// root that already contains a real node_modules folder.
//
// Next's standalone server binds to process.env.HOSTNAME. Shells often set
// HOSTNAME to the machine name, which would bind the wrong interface, so pin
// it to localhost (override with BIND_HOST). PORT comes from the host;
// server.js falls back to 3000.
process.env.HOSTNAME = process.env.BIND_HOST || "127.0.0.1";
process.env.NODE_ENV = "production";

// Plain CommonJS on purpose: Passenger loads this file directly with node.
// eslint-disable-next-line @typescript-eslint/no-require-imports
require("./bundle/server.js");
