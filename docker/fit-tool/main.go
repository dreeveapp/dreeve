// Command fit-tool decodes a Garmin .FIT file and writes its contents to
// stdout as JSON. It is a thin wrapper around github.com/muktihari/fit so the
// PHP application can decode FIT files through a Symfony Process call.
//
// Usage:
//
//	fit-tool <path-to-fit-file>
//
// Output shape:
//
//	{
//	  "files": [
//	    {
//	      "profileVersion": 2132,
//	      "messages": [
//	        {"name": "record", "num": 20, "fields": [
//	          {"name": "position_lat", "num": 0, "units": "semicircles", "value": 533590487},
//	          {"name": "speed", "num": 6, "units": "m/s", "value": 4.123}
//	        ]}
//	      ]
//	    }
//	  ]
//	}
//
// Field values already have their FIT scale/offset applied. Invalid/unset
// numeric fields (encoded as NaN by the FIT profile) are emitted as null so the
// document is always valid JSON. A single .FIT file may contain several chained
// FIT files, hence "files" is an array.
//
// On success it prints the JSON document and exits 0. On any error it prints a
// message to stderr and exits non-zero.
package main

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"os"

	"github.com/muktihari/fit/decoder"
	"github.com/muktihari/fit/kit/scaleoffset"
	"github.com/muktihari/fit/profile/basetype"
	"github.com/muktihari/fit/proto"
)

type outFile struct {
	ProfileVersion uint16       `json:"profileVersion"`
	Messages       []outMessage `json:"messages"`
}

type outMessage struct {
	Name   string     `json:"name"`
	Num    uint16     `json:"num"`
	Fields []outField `json:"fields"`
}

type outField struct {
	Name  string `json:"name"`
	Num   byte   `json:"num"`
	Units string `json:"units,omitempty"`
	Value any    `json:"value"`
}

func main() {
	if len(os.Args) != 2 {
		fmt.Fprintln(os.Stderr, "usage: fit-tool <path-to-fit-file>")
		os.Exit(2)
	}

	if err := run(os.Args[1]); err != nil {
		fmt.Fprintf(os.Stderr, "fit-tool: %v\n", err)
		os.Exit(1)
	}
}

func run(path string) error {
	files, err := decode(path)
	// A bad CRC almost always means a quirky-but-readable file rather than
	// corrupt data, so retry while ignoring the checksum instead of failing.
	if errors.Is(err, decoder.ErrCRCChecksumMismatch) {
		fmt.Fprintf(os.Stderr, "fit-tool: warning: %v; retrying without checksum validation\n", err)
		files, err = decode(path, decoder.WithIgnoreChecksum())
	}
	if err != nil {
		return err
	}

	enc := json.NewEncoder(os.Stdout)
	if err := enc.Encode(map[string]any{"files": files}); err != nil {
		return fmt.Errorf("encode json: %w", err)
	}

	return nil
}

// decode reads every chained FIT file at path into the output shape. The opts
// are forwarded to the decoder so callers can, e.g., relax CRC validation.
func decode(path string, opts ...decoder.Option) ([]outFile, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, fmt.Errorf("open file: %w", err)
	}
	defer f.Close()

	dec := decoder.New(bufio.NewReader(f), opts...)

	files := make([]outFile, 0, 1)
	// A .FIT file may contain multiple chained FIT files; decode them all.
	for dec.Next() {
		fit, err := dec.Decode()
		if err != nil {
			return nil, fmt.Errorf("decode: %w", err)
		}

		file := outFile{
			ProfileVersion: fit.FileHeader.ProfileVersion,
			Messages:       make([]outMessage, 0, len(fit.Messages)),
		}

		for _, mesg := range fit.Messages {
			fields := make([]outField, 0, len(mesg.Fields))
			for _, field := range mesg.Fields {
				if field.FieldBase == nil {
					continue
				}

				fields = append(fields, convertField(field))
			}

			file.Messages = append(file.Messages, outMessage{
				Name:   mesg.Num.String(),
				Num:    uint16(mesg.Num),
				Fields: fields,
			})
		}

		files = append(files, file)
	}

	return files, nil
}

// convertField turns a decoded FIT field into its JSON output form. Fields
// holding the FIT "no value" sentinel are emitted as null; otherwise the
// raw value has its scale/offset applied and is sanitized.
func convertField(field proto.Field) outField {
	out := outField{
		Name:  field.Name,
		Num:   field.Num,
		Units: field.Units,
	}

	raw := field.Value.Any()
	if isInvalid(raw, field.BaseType) {
		out.Value = nil
		return out
	}

	scale := field.Scale
	if scale == 0 {
		scale = 1
	}

	out.Value = sanitize(scaleoffset.ApplyAny(raw, scale, field.Offset))

	return out
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
