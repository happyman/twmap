--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry, geography, and raster spatial types and functions';


SET search_path = public, pg_catalog;

--
-- Name: point_class; Type: TYPE; Schema: public; Owner: docker
--

CREATE TYPE point_class AS ENUM (
    '0',
    '1',
    '2',
    '3',
    '4'
);


ALTER TYPE public.point_class OWNER TO docker;

--
-- Name: ptype; Type: TYPE; Schema: public; Owner: docker
--

CREATE TYPE ptype AS ENUM (
    '一等點',
    '二等點',
    '三等點',
    '森林點',
    '未知森林點',
    '補點',
    '圖根點',
    '無基石山頭',
    '溫泉',
    '湖泊',
    '谷地',
    '溪流',
    '瀑布',
    '獵寮',
    '營地',
    '水源',
    '乾溝',
    '黑水池',
    '積水池',
    '遺跡',
    '舊部落',
    '駐在所',
    '階梯',
    '岩石',
    '崩壁',
    '其他',
    '山屋',
    '吊橋'
);


ALTER TYPE public.ptype OWNER TO docker;

--
-- Name: rock_status; Type: TYPE; Schema: public; Owner: docker
--

CREATE TYPE rock_status AS ENUM (
    '存在',
    '遺失',
    '森林點共用',
    '森林點未知',
    '森林點共存',
    '其他'
);


ALTER TYPE public.rock_status OWNER TO docker;

--
-- Name: isnumeric(text); Type: FUNCTION; Schema: public; Owner: docker
--

CREATE FUNCTION isnumeric(text) RETURNS boolean
    LANGUAGE plpgsql IMMUTABLE
    AS $_$
DECLARE x NUMERIC;
BEGIN
    x = $1::NUMERIC;
    RETURN TRUE;
EXCEPTION WHEN others THEN
    RETURN FALSE;
END;
$_$;


ALTER FUNCTION public.isnumeric(text) OWNER TO docker;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: tw_town_2014; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE tw_town_2014 (
    id integer NOT NULL,
    geom geometry(Polygon,4326),
    "Remark" character varying,
    "C_Name" character varying,
    "Add_Date" character varying,
    "T_Name" character varying,
    "Town_ID" character varying,
    "County_ID" character varying,
    "Add_Accept" character varying,
    name character varying,
    permit boolean,
    cwb_tribe_code text
);


ALTER TABLE public.tw_town_2014 OWNER TO docker;

--
-- Name: TW_TOWN_s_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE "TW_TOWN_s_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public."TW_TOWN_s_id_seq" OWNER TO docker;

--
-- Name: TW_TOWN_s_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE "TW_TOWN_s_id_seq" OWNED BY tw_town_2014.id;


--
-- Name: area; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE area (
    area_id character varying(20) NOT NULL,
    name character varying(40) NOT NULL,
    parent character varying(20) NOT NULL,
    lowerleft_latitude double precision NOT NULL,
    lowerleft_longitude double precision NOT NULL,
    upperright_latitude double precision NOT NULL,
    upperright_longitude double precision NOT NULL,
    description text
);


ALTER TABLE public.area OWNER TO docker;

--
-- Name: contours; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE contours (
    gid integer NOT NULL,
    id integer,
    height double precision,
    way geometry(MultiLineString)
);


ALTER TABLE public.contours OWNER TO docker;

--
-- Name: contours_gid_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE contours_gid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.contours_gid_seq OWNER TO docker;

--
-- Name: contours_gid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE contours_gid_seq OWNED BY contours.gid;


--
-- Name: country_moi_20151215; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE country_moi_20151215 (
    id integer NOT NULL,
    geom geometry(MultiPolygon,4019),
    objectid integer,
    county_id character varying(50),
    shape_leng numeric,
    shape_area numeric,
    c_name character varying(50),
    c_desc character varying(50),
    add_date date,
    add_accept character varying(100),
    remark character varying(254)
);


ALTER TABLE public.country_moi_20151215 OWNER TO docker;

--
-- Name: country_moi_20151215_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE country_moi_20151215_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.country_moi_20151215_id_seq OWNER TO docker;

--
-- Name: country_moi_20151215_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE country_moi_20151215_id_seq OWNED BY country_moi_20151215.id;


--
-- Name: froad; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE froad (
    id integer NOT NULL,
    geom geometry(LineString,4326),
    name character varying,
    "NUM" double precision,
    "AREA" double precision,
    "LEN" double precision
);


ALTER TABLE public.froad OWNER TO docker;

--
-- Name: froad_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE froad_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.froad_id_seq OWNER TO docker;

--
-- Name: froad_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE froad_id_seq OWNED BY froad.id;


--
-- Name: geocoder_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE geocoder_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.geocoder_id_seq OWNER TO docker;

--
-- Name: geocoder; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE geocoder (
    id integer DEFAULT nextval('geocoder_id_seq'::regclass) NOT NULL,
    address character varying(160) NOT NULL,
    lat numeric(10,6) NOT NULL,
    lng numeric(10,6) NOT NULL,
    is_tw integer DEFAULT 0 NOT NULL,
    exact integer DEFAULT 0 NOT NULL,
    faddr text NOT NULL,
    name character varying(120) NOT NULL
);


ALTER TABLE public.geocoder OWNER TO docker;

--
-- Name: gpx_trk; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE gpx_trk (
    ogc_fid integer NOT NULL,
    wkb_geometry geometry(MultiLineString,4326),
    "gpx_trk.name" character varying,
    "gpx_trk.cmt" character varying,
    "gpx_trk.desc" character varying,
    "gpx_trk.src" character varying,
    "gpx_trk.link1_href" character varying,
    "gpx_trk.link1_text" character varying,
    "gpx_trk.link1_type" character varying,
    "gpx_trk.link2_href" character varying,
    "gpx_trk.link2_text" character varying,
    "gpx_trk.link2_type" character varying,
    "gpx_trk.number" integer,
    "gpx_trk.type" character varying,
    mid integer
);


ALTER TABLE public.gpx_trk OWNER TO docker;

--
-- Name: gpx_trk_ogc_fid_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE gpx_trk_ogc_fid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.gpx_trk_ogc_fid_seq OWNER TO docker;

--
-- Name: gpx_trk_ogc_fid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE gpx_trk_ogc_fid_seq OWNED BY gpx_trk.ogc_fid;


--
-- Name: gpx_wp; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE gpx_wp (
    ogc_fid integer NOT NULL,
    wkb_geometry geometry(Point,4326),
    "gpx_wp.ele" double precision,
    "gpx_wp.time" timestamp with time zone,
    "gpx_wp.magvar" double precision,
    "gpx_wp.geoidheight" double precision,
    "gpx_wp.name" character varying,
    "gpx_wp.cmt" character varying,
    "gpx_wp.desc" character varying,
    "gpx_wp.src" character varying,
    "gpx_wp.url" character varying,
    "gpx_wp.urlname" character varying,
    "gpx_wp.sym" character varying,
    "gpx_wp.type" character varying,
    "gpx_wp.fix" character varying,
    "gpx_wp.sat" integer,
    "gpx_wp.hdop" double precision,
    "gpx_wp.vdop" double precision,
    "gpx_wp.pdop" double precision,
    "gpx_wp.ageofdgpsdata" double precision,
    "gpx_wp.dgpsid" integer,
    mid integer
);


ALTER TABLE public.gpx_wp OWNER TO docker;

--
-- Name: gpx_wp_ogc_fid_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE gpx_wp_ogc_fid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.gpx_wp_ogc_fid_seq OWNER TO docker;

--
-- Name: gpx_wp_ogc_fid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE gpx_wp_ogc_fid_seq OWNED BY gpx_wp.ogc_fid;


--
-- Name: mid_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE mid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 99999999999999999
    CACHE 1;


ALTER TABLE public.mid_seq OWNER TO docker;

--
-- Name: map; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE map (
    mid integer DEFAULT nextval('mid_seq'::regclass) NOT NULL,
    uid integer NOT NULL,
    cdate timestamp without time zone DEFAULT now() NOT NULL,
    ddate timestamp without time zone,
    host character varying(200) NOT NULL,
    title text NOT NULL,
    "locX" integer NOT NULL,
    "locY" integer NOT NULL,
    "shiftX" integer NOT NULL,
    "shiftY" integer NOT NULL,
    "pageX" integer NOT NULL,
    "pageY" integer NOT NULL,
    filename character varying(512) NOT NULL,
    size integer NOT NULL,
    version integer NOT NULL,
    flag integer DEFAULT 0 NOT NULL,
    count bigint DEFAULT 0 NOT NULL,
    gpx integer NOT NULL,
    hide integer DEFAULT 0 NOT NULL,
    keepon_id character varying
);


ALTER TABLE public.map OWNER TO docker;

--
-- Name: nature_parks; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE nature_parks (
    id integer NOT NULL,
    geom geometry(MultiPolygon,4326),
    "@id" character varying(254),
    boundary character varying(254),
    name character varying(254),
    name_en character varying(254),
    name_zh character varying(254),
    type character varying(254),
    landuse character varying(254),
    leisure character varying(254),
    status character varying(254),
    wikipedia character varying(254)
);


ALTER TABLE public.nature_parks OWNER TO docker;

--
-- Name: natual_parks_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE natual_parks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.natual_parks_id_seq OWNER TO docker;

--
-- Name: natual_parks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE natual_parks_id_seq OWNED BY nature_parks.id;


--
-- Name: nature_reserve; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE nature_reserve (
    id integer NOT NULL,
    geom geometry(Polygon,4326),
    "Name" character varying,
    "Description" character varying
);


ALTER TABLE public.nature_reserve OWNER TO docker;

--
-- Name: natual_reserves_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE natual_reserves_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.natual_reserves_id_seq OWNER TO docker;

--
-- Name: natual_reserves_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE natual_reserves_id_seq OWNED BY nature_reserve.id;


--
-- Name: point_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE point_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.point_id_seq OWNER TO docker;

--
-- Name: point; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE point (
    id integer DEFAULT nextval('point_id_seq'::regclass) NOT NULL,
    name character varying(100) NOT NULL,
    alias character varying(100) DEFAULT NULL::character varying,
    x double precision NOT NULL,
    y double precision NOT NULL,
    type ptype,
    class point_class NOT NULL,
    number integer,
    status rock_status,
    ele integer,
    mt100 integer DEFAULT 0 NOT NULL,
    checked integer DEFAULT 0 NOT NULL,
    comment text,
    owner integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.point OWNER TO docker;

--
-- Name: point2; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE point2 (
    id integer DEFAULT nextval('point_id_seq'::regclass) NOT NULL,
    name character varying(100) NOT NULL,
    alias character varying(100) DEFAULT NULL::character varying,
    coord geometry(Point,4326),
    type ptype,
    class point_class NOT NULL,
    number integer,
    status rock_status,
    ele integer,
    mt100 integer DEFAULT 0 NOT NULL,
    checked integer DEFAULT 0 NOT NULL,
    comment text,
    owner integer DEFAULT 0 NOT NULL,
    contribute integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.point2 OWNER TO docker;

--
-- Name: point3; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE point3 (
    id integer DEFAULT nextval('point_id_seq'::regclass) NOT NULL,
    name character varying(100) NOT NULL,
    alias character varying(100) DEFAULT NULL::character varying,
    coord geometry(Point,4326),
    type ptype,
    class point_class NOT NULL,
    number integer,
    status rock_status,
    ele integer,
    mt100 integer DEFAULT 0 NOT NULL,
    checked integer DEFAULT 0 NOT NULL,
    comment text,
    owner integer DEFAULT 0 NOT NULL,
    contribute integer DEFAULT 0 NOT NULL,
    mdate timestamp without time zone DEFAULT now(),
    alias2 character varying(100) DEFAULT NULL::character varying
);


ALTER TABLE public.point3 OWNER TO docker;

--
-- Name: riverlin; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE riverlin (
    id integer NOT NULL,
    geom geometry(MultiLineStringZ,4326),
    "Name" character varying,
    "Description" character varying
);


ALTER TABLE public.riverlin OWNER TO docker;

--
-- Name: riverlin_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE riverlin_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.riverlin_id_seq OWNER TO docker;

--
-- Name: riverlin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE riverlin_id_seq OWNED BY riverlin.id;


--
-- Name: riverpoly; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE riverpoly (
    id integer NOT NULL,
    geom geometry(MultiPolygonZ,4326),
    "Name" character varying,
    "Description" character varying
);


ALTER TABLE public.riverpoly OWNER TO docker;

--
-- Name: riverpoly_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE riverpoly_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.riverpoly_id_seq OWNER TO docker;

--
-- Name: riverpoly_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: docker
--

ALTER SEQUENCE riverpoly_id_seq OWNED BY riverpoly.id;


--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: docker
--

CREATE SEQUENCE user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_id_seq OWNER TO docker;

--
-- Name: user; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE "user" (
    uid integer DEFAULT nextval('user_id_seq'::regclass) NOT NULL,
    email character varying(200) NOT NULL,
    type character varying(40) NOT NULL,
    name character varying(160) NOT NULL,
    "limit" integer DEFAULT 20 NOT NULL,
    cdate timestamp without time zone DEFAULT now() NOT NULL,
    login integer NOT NULL
);


ALTER TABLE public."user" OWNER TO docker;

--
-- Name: gid; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY contours ALTER COLUMN gid SET DEFAULT nextval('contours_gid_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY country_moi_20151215 ALTER COLUMN id SET DEFAULT nextval('country_moi_20151215_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY froad ALTER COLUMN id SET DEFAULT nextval('froad_id_seq'::regclass);


--
-- Name: ogc_fid; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY gpx_trk ALTER COLUMN ogc_fid SET DEFAULT nextval('gpx_trk_ogc_fid_seq'::regclass);


--
-- Name: ogc_fid; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY gpx_wp ALTER COLUMN ogc_fid SET DEFAULT nextval('gpx_wp_ogc_fid_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY nature_parks ALTER COLUMN id SET DEFAULT nextval('natual_parks_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY nature_reserve ALTER COLUMN id SET DEFAULT nextval('natual_reserves_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY riverlin ALTER COLUMN id SET DEFAULT nextval('riverlin_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY riverpoly ALTER COLUMN id SET DEFAULT nextval('riverpoly_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: docker
--

ALTER TABLE ONLY tw_town_2014 ALTER COLUMN id SET DEFAULT nextval('"TW_TOWN_s_id_seq"'::regclass);


--
-- Name: TW_TOWN_s_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY tw_town_2014
    ADD CONSTRAINT "TW_TOWN_s_pkey" PRIMARY KEY (id);


--
-- Name: contours_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY contours
    ADD CONSTRAINT contours_pkey PRIMARY KEY (gid);


--
-- Name: country_moi_20151215_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY country_moi_20151215
    ADD CONSTRAINT country_moi_20151215_pkey PRIMARY KEY (id);


--
-- Name: froad_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY froad
    ADD CONSTRAINT froad_pkey PRIMARY KEY (id);


--
-- Name: geocoder_address_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY geocoder
    ADD CONSTRAINT geocoder_address_key UNIQUE (address);


--
-- Name: geocoder_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY geocoder
    ADD CONSTRAINT geocoder_pkey PRIMARY KEY (id);


--
-- Name: gpx_trk_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY gpx_trk
    ADD CONSTRAINT gpx_trk_pkey PRIMARY KEY (ogc_fid);


--
-- Name: gpx_wp_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY gpx_wp
    ADD CONSTRAINT gpx_wp_pkey PRIMARY KEY (ogc_fid);


--
-- Name: map_mid_pk; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_mid_pk PRIMARY KEY (mid);


--
-- Name: natual_parks_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY nature_parks
    ADD CONSTRAINT natual_parks_pkey PRIMARY KEY (id);


--
-- Name: natual_reserves_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY nature_reserve
    ADD CONSTRAINT natual_reserves_pkey PRIMARY KEY (id);


--
-- Name: point2_name_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point2
    ADD CONSTRAINT point2_name_key UNIQUE (name);


--
-- Name: point2_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point2
    ADD CONSTRAINT point2_pkey PRIMARY KEY (id);


--
-- Name: point3_name_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point3
    ADD CONSTRAINT point3_name_key UNIQUE (name);


--
-- Name: point3_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point3
    ADD CONSTRAINT point3_pkey PRIMARY KEY (id);


--
-- Name: point_name_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point
    ADD CONSTRAINT point_name_key UNIQUE (name);


--
-- Name: point_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point
    ADD CONSTRAINT point_pkey PRIMARY KEY (id);


--
-- Name: riverlin_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY riverlin
    ADD CONSTRAINT riverlin_pkey PRIMARY KEY (id);


--
-- Name: riverpoly_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY riverpoly
    ADD CONSTRAINT riverpoly_pkey PRIMARY KEY (id);


--
-- Name: user_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (uid);


--
-- Name: contours_way_idx; Type: INDEX; Schema: public; Owner: docker; Tablespace: 
--

CREATE INDEX contours_way_idx ON contours USING gist (way);


--
-- Name: gpx_trk_wkb_geometry_geom_idx; Type: INDEX; Schema: public; Owner: docker; Tablespace: 
--

CREATE INDEX gpx_trk_wkb_geometry_geom_idx ON gpx_trk USING gist (wkb_geometry);


--
-- Name: gpx_wp_wkb_geometry_geom_idx; Type: INDEX; Schema: public; Owner: docker; Tablespace: 
--

CREATE INDEX gpx_wp_wkb_geometry_geom_idx ON gpx_wp USING gist (wkb_geometry);


--
-- Name: sidx_country_moi_20151215_geom; Type: INDEX; Schema: public; Owner: docker; Tablespace: 
--

CREATE INDEX sidx_country_moi_20151215_geom ON country_moi_20151215 USING gist (geom);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

