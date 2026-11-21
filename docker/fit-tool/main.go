// Command fit-tool decodes a Garmin .FIT file read from stdin and writes its
// contents to stdout as JSON. It is a thin wrapper around github.com/muktihari/fit so the
// PHP application can decode FIT files through a Symfony Process call.
//
// Usage:
//
//	fit-tool < activity.fit
//
// Output shape, one JSON object per FIT message, one message per line:
//
//	{"name":"record","fields":{"position_lat":533590487,"speed":4.123}}
//
// Field values already have their FIT scale/offset applied. Invalid/unset
// fields (the FIT "no value" sentinel, or a non-finite float) are left out of
// "fields"; inside array values they are emitted as null so every line is
// valid JSON. When a message holds the same field name twice,
// the last value wins. A single .FIT file may contain several chained FIT
// files; their messages are written one after another.
//
// On success it prints the messages and exits 0. On any error it prints a
// message to stderr and exits non-zero.
package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"math"
	"os"

	"github.com/muktihari/fit/decoder"
	"github.com/muktihari/fit/kit/scaleoffset"
	"github.com/muktihari/fit/profile/basetype"
	"github.com/muktihari/fit/proto"
)

type outMessage struct {
	Name   string         `json:"name"`
	Fields map[string]any `json:"fields"`
}

func main() {
	if len(os.Args) != 1 {
		fmt.Fprintln(os.Stderr, "usage: fit-tool < activity.fit")
		os.Exit(2)
	}

	if err := run(os.Stdin); err != nil {
		fmt.Fprintf(os.Stderr, "fit-tool: %v\n", err)
		os.Exit(1)
	}
}

func run(r io.Reader) error {
	data, err := io.ReadAll(r)
	if err != nil {
		return fmt.Errorf("read input: %w", err)
	}

	out, err := decode(data)
	// A bad CRC almost always means a quirky-but-readable file rather than
	// corrupt data, so retry while ignoring the checksum instead of failing.
	if errors.Is(err, decoder.ErrCRCChecksumMismatch) {
		fmt.Fprintf(os.Stderr, "fit-tool: warning: %v; retrying without checksum validation\n", err)
		out, err = decode(data, decoder.WithIgnoreChecksum())
	}
	if err != nil {
		return err
	}

	if _, err := out.WriteTo(os.Stdout); err != nil {
		return fmt.Errorf("write output: %w", err)
	}

	return nil
}

// decode writes every message of every chained FIT file in data to a buffer,
// one JSON object per line. Each attempt gets its own buffer, so a failed
// attempt never leaves partial output. The opts are forwarded to the decoder
// so callers can, e.g., relax CRC validation.
func decode(data []byte, opts ...decoder.Option) (*bytes.Buffer, error) {
	dec := decoder.New(bytes.NewReader(data), opts...)

	out := new(bytes.Buffer)
	enc := json.NewEncoder(out)
	// A .FIT file may contain multiple chained FIT files; decode them all.
	for dec.Next() {
		fit, err := dec.Decode()
		if err != nil {
			return nil, fmt.Errorf("decode: %w", err)
		}

		for _, mesg := range fit.Messages {
			fields := make(map[string]any, len(mesg.Fields))
			for _, field := range mesg.Fields {
				if field.FieldBase == nil {
					continue
				}

				value := convertValue(field)
				if value == nil {
					continue
				}

				fields[field.Name] = value
			}

			if err := enc.Encode(outMessage{Name: mesg.Num.String(), Fields: fields}); err != nil {
				return nil, fmt.Errorf("encode json: %w", err)
			}
		}
	}

	return out, nil
}

// convertValue turns a decoded FIT field into its JSON output value. Fields
// holding the FIT "no value" sentinel return nil; otherwise the
// raw value has its scale/offset applied and is sanitized.
func convertValue(field proto.Field) any {
	raw := field.Value.Any()
	if isInvalid(raw, field.BaseType) {
		return nil
	}

	scale := field.Scale
	if scale == 0 {
		scale = 1
	}

	return sanitize(scaleoffset.ApplyAny(raw, scale, field.Offset))
}

// isInvalid reports whether raw is the FIT "no value" sentinel for its base
// type. Integer and byte fields encode "unset" as a max-value sentinel (e.g.
// uint16 -> 0xFFFF = 65535) which must be nulled out before scale/offset turns
// it into a plausible-looking number. Float sentinels decode to NaN and are
// handled by sanitize instead, so they are treated as valid here.
func isInvalid(raw any, bt basetype.BaseType) bool {
	invalid := bt.Invalid()
	switch invalid.(type) {
	case float32, float64:
		return false
	}

	return raw == invalid
}

// sanitize replaces non-finite floats (the FIT "invalid" sentinel) with nil so
// the result is always valid JSON, recursing into float slices. Byte slices
// (e.g. the hr message's filtered_bpm samples) are widened to number slices
// because encoding/json would otherwise emit []uint8 as a base64 string.
func sanitize(v any) any {
	switch x := v.(type) {
	case float64:
		return finiteOrNil(x)
	case float32:
		return finiteOrNil(float64(x))
	case []uint8:
		out := make([]any, len(x))
		for i, e := range x {
			out[i] = e
		}
		return out
	case []float64:
		out := make([]any, len(x))
		for i, e := range x {
			out[i] = finiteOrNil(e)
		}
		return out
	case []float32:
		out := make([]any, len(x))
		for i, e := range x {
			out[i] = finiteOrNil(float64(e))
		}
		return out
	default:
		return v
	}
}

func finiteOrNil(f float64) any {
	if math.IsNaN(f) || math.IsInf(f, 0) {
		return nil
	}
	return f
}
