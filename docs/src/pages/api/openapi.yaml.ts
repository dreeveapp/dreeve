import path from 'node:path';
import { bundle } from '@readme/openapi-parser';
import type { APIRoute } from 'astro';
import { stringify } from 'yaml';

export const GET: APIRoute = async () => {
	const schema = await bundle(path.resolve(process.cwd(), 'src/schemas/dreeve-api.yaml'));

	return new Response(stringify(schema, { lineWidth: 0 }), {
		headers: { 'Content-Type': 'application/yaml; charset=utf-8' },
	});
};
