export type AttachmentSummary = {
    id: number;
    label: string;
    file_name: string;
    size_bytes: number;
    mime_type: string;
    /**
     * The server's answer, from the type it sniffed at upload — not something
     * worked out here from the file name. It decides whether the browser is
     * asked to render the bytes.
     */
    is_image: boolean;
    uploader: string | null;
    uploaded_at: string | null;
};

/** What the upload control may accept, read from the server's own config. */
export type AttachmentRules = {
    max_kilobytes: number;
    extensions: string[];
};
