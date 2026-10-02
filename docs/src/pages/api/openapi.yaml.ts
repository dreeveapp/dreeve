import type { APIRoute } from 'astro';
import schema from '../../schemas/dreeve-api.yaml?raw';

export const GET: APIRoute = () =>
	new Response(schema, { headers: { 'Content-Type': 'application/yaml; charset=utf-8' } });
